<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Http\Controller\AddressController;
use App\Http\ErrorMap;
use App\Http\Middleware\Cors;
use App\Http\Middleware\IpGuard;
use App\Http\Middleware\RateLimiter;
use App\Http\Middleware\RequestId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$varPath = dirname(__DIR__) . '/var';
if (!is_dir($varPath) && !mkdir($varPath, 0775, true) && !is_dir($varPath)) {
    ErrorMap::emit(500, 'runtime', 'failed_to_create_var_directory', ['path' => $varPath]);
    exit(0);
}

$limitPdo = new PDO('sqlite:' . $varPath . '/rate-limit.sqlite');
$limitPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pgDsn = (string) (getenv('PG_DSN') ?: getenv('DB_DSN'));
if ($pgDsn === '') {
    ErrorMap::emit(500, 'missing_pg_dsn', 'missing_pg_dsn', [
        'hint' => 'Set PG_DSN (or DB_DSN) to a Postgres connection string.',
    ]);
    exit(0);
}

$pgUser = (string) (getenv('PG_USER') ?: '');
$pgPass = (string) (getenv('PG_PASS') ?: '');

$pg = new PDO($pgDsn, $pgUser === '' ? null : $pgUser, $pgPass === '' ? null : $pgPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$request = Request::createFromGlobals();
$method = $request->getMethod();
$pathInfo = $request->getPathInfo();

RequestId::ensure();
Cors::handle($request, $method);

$clientIp = (string) ($request->server->get('REMOTE_ADDR') ?? '0.0.0.0');
if (!IpGuard::allowed($clientIp, $pathInfo)) {
    ErrorMap::emit(403, 'forbidden', 'ip_forbidden');
    exit(0);
}

$rateLimiter = new RateLimiter($limitPdo);
if (!$rateLimiter->check($clientIp, $method . ' ' . $pathInfo)) {
    ErrorMap::emit(429, 'too_many_requests', 'rate_limit_exceeded');
    exit(0);
}

$controller = AddressController::fromPg($pg);

try {
    if ($pathInfo === '/address/manage' && ($method === 'GET' || $method === 'POST')) {
        $controller->manage($request)->send();
        exit(0);
    }

    if ($method === 'POST' && $pathInfo === '/api/address') {
        $controller->create($request)->send();
        exit(0);
    }

    if ($method === 'GET' && ($pathInfo === '/api/address/page' || $pathInfo === '/api/address/search')) {
        $controller->page($request)->send();
        exit(0);
    }

    if ($method === 'GET' && preg_match('#^/api/address/([0-9A-HJKMNP-TV-Z]{26}|demo-[0-9]{4})$#', $pathInfo, $matches) === 1) {
        $controller->get($request, $matches[1])->send();
        exit(0);
    }

    if ($method === 'DELETE' && preg_match('#^/api/address/([0-9A-HJKMNP-TV-Z]{26}|demo-[0-9]{4})$#', $pathInfo, $matches) === 1) {
        $controller->markDeleted($request, $matches[1])->send();
        exit(0);
    }

    if ($method === 'POST' && preg_match('#^/api/address/([0-9A-HJKMNP-TV-Z]{26}|demo-[0-9]{4})/validated$#', $pathInfo, $matches) === 1) {
        $controller->applyValidated($request, $matches[1])->send();
        exit(0);
    }

    (new JsonResponse(['error' => 'not_found'], 404))->send();
} catch (RuntimeException $exception) {
    $code = $exception->getMessage();

    if ($code === 'not_found') {
        ErrorMap::emit(404, $code, $code);
        exit(0);
    }

    if (str_starts_with($code, 'missing_') || str_starts_with($code, 'invalid_')) {
        ErrorMap::emit(400, $code, $code);
        exit(0);
    }

    ErrorMap::emit(500, 'runtime', $code);
} catch (Throwable $exception) {
    ErrorMap::emit(500, 'unhandled', $exception->getMessage());
}
