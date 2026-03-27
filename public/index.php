<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Http\Controller\AddressController;
use App\Http\ErrorMap;
use App\Http\Middleware\Cors;
use App\Http\Middleware\IpGuard;
use App\Http\Middleware\RateLimiter;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\SecurityHeaders;
use App\Integration\Persistence\AddressPdoFactory;
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload.php';

if (class_exists(Dotenv::class) && file_exists(dirname(__DIR__).'/.env')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

$_SERVER['APP_ENV'] ??= 'dev';
$_SERVER['APP_DEBUG'] ??= '1';

$request = Request::createFromGlobals();
$method = $request->getMethod();
$pathInfo = $request->getPathInfo();

RequestId::ensure();
Cors::handle($request, $method);
SecurityHeaders::apply();

$clientIp = (string) ($request->server->get('REMOTE_ADDR') ?? '0.0.0.0');
if (!IpGuard::allowed($clientIp, $pathInfo)) {
    ErrorMap::emit(403, 'forbidden', 'ip_forbidden');
    exit(0);
}

$rateLimiter = new RateLimiter(AddressPdoFactory::createRateLimit());
if (!$rateLimiter->check($clientIp, $method.' '.$pathInfo)) {
    ErrorMap::emit(429, 'too_many_requests', 'rate_limit_exceeded');
    exit(0);
}

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$controller = $kernel->getContainer()->get(AddressController::class);

try {
    if ('/address/manage' === $pathInfo && ('GET' === $method || 'POST' === $method)) {
        $controller->manage($request)->send();
        exit(0);
    }

    if ('POST' === $method && '/api/address' === $pathInfo) {
        $controller->create($request)->send();
        exit(0);
    }

    if ('GET' === $method && ('/api/address/page' === $pathInfo || '/api/address/search' === $pathInfo)) {
        $controller->page($request)->send();
        exit(0);
    }

    if ('GET' === $method && '/api/address/queue-summary' === $pathInfo) {
        $controller->queueSummary($request)->send();
        exit(0);
    }

    if ('GET' === $method && '/api/address/country-portfolio' === $pathInfo) {
        $controller->countryPortfolioSummary($request)->send();
        exit(0);
    }

    if ('GET' === $method && '/api/address/source-portfolio' === $pathInfo) {
        $controller->sourcePortfolioSummary($request)->send();
        exit(0);
    }

    if ('GET' === $method && '/api/address/validation-portfolio' === $pathInfo) {
        $controller->validationPortfolioSummary($request)->send();
        exit(0);
    }

    if ('GET' === $method && '/api/address/normalization-portfolio' === $pathInfo) {
        $controller->normalizationPortfolioSummary($request)->send();
        exit(0);
    }

    if ('POST' === $method && '/api/address/operational-batch' === $pathInfo) {
        $controller->patchOperationalBatch($request)->send();
        exit(0);
    }

    if (1 === preg_match('#^/api/address/([0-9A-HJKMNP-TV-Z]{26}|demo-[0-9]{4})$#', $pathInfo, $matches)) {
        if ('GET' === $method) {
            $controller->get($request, $matches[1])->send();
            exit(0);
        }

        if ('DELETE' === $method) {
            $controller->markDeleted($request, $matches[1])->send();
            exit(0);
        }

        if ('PATCH' === $method) {
            $controller->patchOperational($request, $matches[1])->send();
            exit(0);
        }
    }

    if (1 === preg_match('#^/api/address/([0-9A-HJKMNP-TV-Z]{26}|demo-[0-9]{4})/validated$#', $pathInfo, $matches) && 'POST' === $method) {
        $controller->applyValidated($request, $matches[1])->send();
        exit(0);
    }

    if (1 === preg_match('#^/api/address/([0-9A-HJKMNP-TV-Z]{26}|demo-[0-9]{4})/governance-cluster$#', $pathInfo, $matches) && 'GET' === $method) {
        $controller->governanceClusterSummary($request, $matches[1])->send();
        exit(0);
    }

    (new JsonResponse(['error' => 'not_found'], 404))->send();
} catch (RuntimeException $exception) {
    $code = $exception->getMessage();

    if ('not_found' === $code) {
        ErrorMap::emit(404, $code, $code);
        exit(0);
    }

    if (str_starts_with($code, 'missing_') || str_starts_with($code, 'invalid_') || 'tenant_scope_required' === $code) {
        ErrorMap::emit(400, $code, $code);
        exit(0);
    }

    ErrorMap::emit(500, 'runtime', $code);
} catch (Throwable $exception) {
    ErrorMap::emit(500, 'unhandled', $exception->getMessage());
}
