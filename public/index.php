<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

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
if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
    ErrorMap::emit(500, 'runtime', 'failed_to_create_var_directory', ['path' => $path]);
    exit(0);
}

$limitPdo = new PDO('sqlite:' . $path . '/rate-limit.sqlite');
$limitPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pgDsn = (string)(getenv('PG_DSN') ?: getenv('DB_DSN'));
if ($pgDsn === '') {
    ErrorMap::emit(500, 'missing_pg_dsn', 'missing_pg_dsn', [
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

$request = Request::createFromGlobals();

(new RequestId())->handle($request);
Cors::handle($request);
(new IpGuard())->handle($request);
(new RateLimiter($limitPdo))->handle($request);

$controller = Controller::fromPg($pg);

$method = $request->getMethod();
$pathInfo = $request->getPathInfo();

try {
    if ($method === 'POST' && $pathInfo === '/api/address') {
        $controller->create($request)->send();
        exit(0);
    }

    if ($method === 'GET' && ($pathInfo === '/api/address/page' || $pathInfo === '/api/address/search')) {
        $controller->page($request)->send();
        exit(0);
    }

    if ($method === 'GET' && preg_match('#^/api/address/([0-9A-HJKMNP-TV-Z]{26})$#', $pathInfo, $matches) === 1) {
        $controller->get($request, $matches[1])->send();
        exit(0);
    }

    if ($method === 'DELETE' && preg_match('#^/api/address/([0-9A-HJKMNP-TV-Z]{26})$#', $pathInfo, $matches) === 1) {
        $controller->markDeleted($request, $matches[1])->send();
        exit(0);
    }

    if ($method === 'POST' && preg_match('#^/api/address/([0-9A-HJKMNP-TV-Z]{26})/validated$#', $pathInfo, $matches) === 1) {
        $controller->applyValidated($request, $matches[1])->send();
        exit(0);
    }

    (new JsonResponse(['error' => 'not_found'], 404))->send();
} catch (RuntimeException $e) {
    $code = $e->getMessage();

    if ($code === 'not_found') {
        ErrorMap::emit(404, $code, $code);
        exit(0);
    }

    if (str_starts_with($code, 'missing_') || str_starts_with($code, 'invalid_')) {
        ErrorMap::emit(400, $code, $code);
        exit(0);
    }

    ErrorMap::emit(500, 'runtime', $code);
} catch (Throwable $e) {
    ErrorMap::emit(500, 'unhandled', $e->getMessage());
}
