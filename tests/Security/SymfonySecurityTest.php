<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Security;

use App\Http\Middleware\IpGuard;
use App\Http\Middleware\RateLimiter;
use PDO;
use PHPUnit\Framework\TestCase;

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

        self::assertFalse(IpGuard::allowed('10.0.0.1', '/api/address'));
        self::assertTrue(IpGuard::allowed('10.0.0.2', '/api/address'));
    }

    public function testRateLimiterBlocksAfterBurstLimit(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $limiter = new RateLimiter($pdo, 2, 1);

        self::assertTrue($limiter->check('client-1', 'address_lookup'));
        self::assertTrue($limiter->check('client-1', 'address_lookup'));
        self::assertTrue($limiter->check('client-1', 'address_lookup'));
        self::assertFalse($limiter->check('client-1', 'address_lookup'));
    }
}
