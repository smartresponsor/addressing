<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Addressing\Middleware\AddressIpGuardMiddleware;
use PHPUnit\Framework\TestCase;

final class AddressIpGuardMiddlewareCoverageTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('DENY_IPS');
        putenv('ALLOW_IPS');
        putenv('ALLOW_PATHS');
    }

    public function testPrivateEnvironmentListParserCoversMissingBlankAndFilteredValues(): void
    {
        $method = new \ReflectionMethod(AddressIpGuardMiddleware::class, 'listFromEnv');

        putenv('ALLOW_IPS');
        self::assertSame([], $method->invoke(null, 'ALLOW_IPS'));

        putenv('ALLOW_IPS=   ');
        self::assertSame([], $method->invoke(null, 'ALLOW_IPS'));

        putenv('ALLOW_IPS= 192.0.2.1, ,192.0.2.2 ');
        self::assertSame(['192.0.2.1', '192.0.2.2'], $method->invoke(null, 'ALLOW_IPS'));
    }

    public function testAllowedCoversDenyAllowAndPathPolicyOutcomes(): void
    {
        putenv('DENY_IPS=192.0.2.9');
        self::assertFalse(AddressIpGuardMiddleware::allowed('192.0.2.9', '/api/address'));

        putenv('DENY_IPS=');
        putenv('ALLOW_IPS=192.0.2.1');
        self::assertFalse(AddressIpGuardMiddleware::allowed('192.0.2.2', '/api/address'));

        putenv('ALLOW_IPS=192.0.2.1');
        putenv('ALLOW_PATHS=/api/address,/health');
        self::assertTrue(AddressIpGuardMiddleware::allowed('192.0.2.1', '/health/ready'));
        self::assertFalse(AddressIpGuardMiddleware::allowed('192.0.2.1', '/admin'));

        putenv('ALLOW_PATHS=');
        self::assertTrue(AddressIpGuardMiddleware::allowed('192.0.2.1', '/admin'));
    }
}
