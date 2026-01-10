<?php
declare(strict_types=1);

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 */

use App\Http\AddressApi\Controller;
use App\Http\ErrorMap;
use App\Http\Middleware\Cors;
use App\Http\Middleware\IpGuard;
use App\Http\Middleware\RateLimiter;
use App\Http\Middleware\RequestId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$path = dirname(__DIR__) . '/var';
if (!is_dir($path)) {
    @mkdir($path, 0775, true);
}

$limitPdo = new PDO('sqlite:' . $path . '/rate-limit.sqlite');
$limitPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pgDsn = (string)(getenv('PG_DSN') ?: getenv('DB_DSN'));
if ($pgDsn === '') {
    ErrorMap::emit(500, 'missing_pg_dsn', [
        'hint' => 'Set PG_DSN (or DB_DSN) to a Postgres connection string.',
    ]);
    exit(0);
}

$pgUser = (string)(getenv('PG_USER') ?: '');
$pgPass = (string)(getenv('PG_PASS') ?: '');

$pg = new PDO($pgDsn, $pgUser === '' ? null : $pgUser, $pgPass === '' ? null : $pgPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$req = Request::createFromGlobals();

(new RequestId())->handle($req);
Cors::handle($req);
(new IpGuard())->handle($req);
(new RateLimiter($limitPdo))->handle($req);

$controller = Controller::fromPg($pg);

$method = $req->getMethod();
$pathInfo = $req->getPathInfo();

try {
    if ($method === 'POST' && $pathInfo === '/api/address') {
        $controller->create($req)->send();
        exit(0);
    }

    if ($method === 'GET' && ($pathInfo === '/api/address/page' || $pathInfo === '/api/address/search')) {
        $controller->page($req)->send();
        exit(0);
    }

    if ($method === 'GET' && preg_match('#^/api/address/([0-9A-HJKMNP-TV-Z]{26})$#', $pathInfo, $m) === 1) {
        $controller->get($req, $m[1])->send();
        exit(0);
    }

    if ($method === 'DELETE' && preg_match('#^/api/address/([0-9A-HJKMNP-TV-Z]{26})$#', $pathInfo, $m) === 1) {
        $controller->delete($req, $m[1])->send();
        exit(0);
    }

    if ($method === 'POST' && preg_match('#^/api/address/([0-9A-HJKMNP-TV-Z]{26})/validated$#', $pathInfo, $m) === 1) {
        $controller->applyValidated($req, $m[1])->send();
        exit(0);
    }

    (new JsonResponse(['error' => 'not_found'], 404))->send();
} catch (RuntimeException $e) {
    $code = $e->getMessage();
    if ($code === 'not_found') {
        ErrorMap::emit(404, $code);
        exit(0);
    }
    if (str_starts_with($code, 'missing_') || str_starts_with($code, 'invalid_')) {
        ErrorMap::emit(400, $code);
        exit(0);
    }
    ErrorMap::emit(500, 'runtime', ['message' => $code]);
} catch (Throwable $e) {
    ErrorMap::emit(500, 'unhandled', (string)['message' => $e->getMessage()]);
}
