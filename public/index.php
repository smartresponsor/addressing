<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Addressing\Responder\AddressErrorMap;
use App\Addressing\Middleware\AddressCorsMiddleware;
use App\Addressing\Middleware\AddressIpGuardMiddleware;
use App\Addressing\Service\Http\Address\AddressRateLimiterService;
use App\Addressing\Middleware\AddressRequestIdMiddleware;
use App\Addressing\Middleware\AddressSecurityHeadersMiddleware;
use App\Addressing\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

require_once dirname(__DIR__).'/vendor/autoload.php';

if (class_exists(Dotenv::class) && file_exists(dirname(__DIR__).'/.env')) {
    new Dotenv()->bootEnv(dirname(__DIR__).'/.env');
}

$_SERVER['APP_ENV'] ??= 'dev';
$_SERVER['APP_DEBUG'] ??= '1';

$request = Request::createFromGlobals();
$method = $request->getMethod();
$pathInfo = $request->getPathInfo();

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

try {
    AddressRequestIdMiddleware::ensure();
} catch (Throwable) {
    AddressErrorMap::emit(500, 'server_error', 'request_id_failed');
    exit(1);
}
AddressCorsMiddleware::handle($request, $method);
AddressSecurityHeadersMiddleware::apply();

$clientIp = (string) ($request->server->get('REMOTE_ADDR') ?? '0.0.0.0');
if (!AddressIpGuardMiddleware::allowed($clientIp, $pathInfo)) {
    AddressErrorMap::emit(403, 'forbidden', 'ip_forbidden');
    exit(0);
}

$rateLimiter = $kernel->getContainer()->get(AddressRateLimiterService::class);
if (!filter_var($_SERVER['RATE_LIMIT_DISABLED'] ?? getenv('RATE_LIMIT_DISABLED') ?? false, FILTER_VALIDATE_BOOL)
    && !$rateLimiter->check($clientIp, $method.' '.$pathInfo)
) {
    AddressErrorMap::emit(429, 'too_many_requests', 'rate_limit_exceeded');
    exit(0);
}

try {
    $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
    $response->send();
    $kernel->terminate($request, $response);
} catch (RuntimeException $exception) {
    $code = $exception->getMessage();

    if ('not_found' === $code) {
        AddressErrorMap::emit(404, $code, $code);
        exit(0);
    }

    if (str_starts_with($code, 'missing_') || str_starts_with($code, 'invalid_') || 'tenant_scope_required' === $code) {
        AddressErrorMap::emit(400, $code, $code);
        exit(0);
    }

    AddressErrorMap::emit(500, 'runtime', $code);
} catch (Throwable $exception) {
    AddressErrorMap::emit(500, 'unhandled', $exception->getMessage());
}
