<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Http\AddressErrorMap;
use App\Http\Middleware\AddressCorsMiddleware;
use App\Http\Middleware\AddressIpGuardMiddleware;
use App\Http\Middleware\AddressRateLimiter;
use App\Http\Middleware\AddressRequestIdMiddleware;
use App\Http\Middleware\AddressSecurityHeadersMiddleware;
use App\Kernel;
use App\Service\Http\Address\AddressManageHttpService;
use App\Service\Http\Address\AddressOperationalHttpService;
use App\Service\Http\Address\AddressReadHttpService;
use App\Service\Http\Address\AddressSummaryHttpService;
use App\Service\Http\Address\AddressWriteHttpService;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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

$rateLimiter = $kernel->getContainer()->get(AddressRateLimiter::class);
if (!filter_var($_SERVER['RATE_LIMIT_DISABLED'] ?? getenv('RATE_LIMIT_DISABLED') ?? false, FILTER_VALIDATE_BOOL)
    && !$rateLimiter->check($clientIp, $method.' '.$pathInfo)
) {
    AddressErrorMap::emit(429, 'too_many_requests', 'rate_limit_exceeded');
    exit(0);
}

$addressManageHttpService = $kernel->getContainer()->get(AddressManageHttpService::class);
$addressWriteHttpService = $kernel->getContainer()->get(AddressWriteHttpService::class);
$addressReadHttpService = $kernel->getContainer()->get(AddressReadHttpService::class);
$addressSummaryHttpService = $kernel->getContainer()->get(AddressSummaryHttpService::class);
$addressOperationalHttpService = $kernel->getContainer()->get(AddressOperationalHttpService::class);

try {
    if ('/address/manage' === $pathInfo && ('GET' === $method || 'POST' === $method)) {
        $addressManageHttpService->manage($request)->send();
        exit(0);
    }

    if ('POST' === $method && '/api/address' === $pathInfo) {
        $addressWriteHttpService->create($request)->send();
        exit(0);
    }

    if ('GET' === $method && ('/api/address/page' === $pathInfo || '/api/address/search' === $pathInfo)) {
        $addressReadHttpService->page($request)->send();
        exit(0);
    }

    if ('GET' === $method && '/api/address/queue-summary' === $pathInfo) {
        $addressSummaryHttpService->queueSummary($request)->send();
        exit(0);
    }

    if ('GET' === $method && '/api/address/country-portfolio' === $pathInfo) {
        $addressSummaryHttpService->countryPortfolioSummary($request)->send();
        exit(0);
    }

    if ('GET' === $method && '/api/address/source-portfolio' === $pathInfo) {
        $addressSummaryHttpService->sourcePortfolioSummary($request)->send();
        exit(0);
    }

    if ('GET' === $method && '/api/address/validation-portfolio' === $pathInfo) {
        $addressSummaryHttpService->validationPortfolioSummary($request)->send();
        exit(0);
    }

    if ('GET' === $method && '/api/address/normalization-portfolio' === $pathInfo) {
        $addressSummaryHttpService->normalizationPortfolioSummary($request)->send();
        exit(0);
    }

    if ('POST' === $method && '/api/address/operational-batch' === $pathInfo) {
        $addressOperationalHttpService->patchOperationalBatch($request)->send();
        exit(0);
    }

    if (1 === preg_match('#^/api/address/([0-9A-HJKMNP-TV-Z]{26}|demo-[0-9]{4})$#', $pathInfo, $matches)) {
        if ('GET' === $method) {
            $addressReadHttpService->get($request, $matches[1])->send();
            exit(0);
        }

        if ('DELETE' === $method) {
            $addressWriteHttpService->markDeleted($request, $matches[1])->send();
            exit(0);
        }

        if ('PATCH' === $method) {
            $addressOperationalHttpService->patchOperational($request, $matches[1])->send();
            exit(0);
        }
    }

    if (1 === preg_match('#^/api/address/([0-9A-HJKMNP-TV-Z]{26}|demo-[0-9]{4})/validated$#', $pathInfo, $matches) && 'POST' === $method) {
        $addressOperationalHttpService->applyValidated($request, $matches[1])->send();
        exit(0);
    }

    if (1 === preg_match('#^/api/address/([0-9A-HJKMNP-TV-Z]{26}|demo-[0-9]{4})/governance-cluster$#', $pathInfo, $matches) && 'GET' === $method) {
        $addressSummaryHttpService->governanceClusterSummary($request, $matches[1])->send();
        exit(0);
    }

    new JsonResponse(['error' => 'not_found'], 404)->send();
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
