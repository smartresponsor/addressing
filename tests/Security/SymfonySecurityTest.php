<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Security;

use App\Entity\RateLimitEntity;
use App\Http\Middleware\AddressIpGuardMiddleware;
use App\Http\Middleware\AddressRateLimiter;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class SymfonySecurityTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('DENY_IPS');
        putenv('ALLOW_IPS');
        putenv('ALLOW_PATHS');
    }

    public function testIpGuardRejectsDeniedIp(): void
    {
        putenv('DENY_IPS=10.0.0.1');

        self::assertFalse(AddressIpGuardMiddleware::allowed('10.0.0.1', '/api/address'));
        self::assertTrue(AddressIpGuardMiddleware::allowed('10.0.0.2', '/api/address'));
    }

    public function testRateLimiterBlocksAfterBurstLimit(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([RateLimitEntity::class]);
        $limiter = new AddressRateLimiter($entityManager, 2, 1);

        self::assertTrue($limiter->check('client-1', 'address_lookup'));
        self::assertTrue($limiter->check('client-1', 'address_lookup'));
        self::assertTrue($limiter->check('client-1', 'address_lookup'));
        self::assertFalse($limiter->check('client-1', 'address_lookup'));
    }
}
