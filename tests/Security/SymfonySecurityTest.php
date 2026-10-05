<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Security;

use App\Addressing\Entity\AddressRateLimitEntity;
use App\Addressing\Middleware\AddressIpGuardMiddleware;
use App\Addressing\Repository\AddressDoctrineRateLimitRepository;
use App\Addressing\Service\Http\Address\AddressRateLimiterService;
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

    public function testIpGuardHonorsAllowListsAndPathPrefixes(): void
    {
        self::assertTrue(AddressIpGuardMiddleware::allowed('192.0.2.10', '/anything'));

        putenv('ALLOW_IPS=192.0.2.10, 192.0.2.11');
        self::assertTrue(AddressIpGuardMiddleware::allowed('192.0.2.10', '/api/address'));
        self::assertFalse(AddressIpGuardMiddleware::allowed('192.0.2.12', '/api/address'));

        putenv('ALLOW_PATHS=/api/address,/health');
        self::assertTrue(AddressIpGuardMiddleware::allowed('192.0.2.10', '/api/address/123'));
        self::assertTrue(AddressIpGuardMiddleware::allowed('192.0.2.10', '/health/ready'));
        self::assertFalse(AddressIpGuardMiddleware::allowed('192.0.2.10', '/admin'));
    }

    public function testIpGuardIgnoresBlankEnvironmentEntries(): void
    {
        putenv('DENY_IPS= , ');
        putenv('ALLOW_IPS= , ');
        putenv('ALLOW_PATHS= , ');

        self::assertTrue(AddressIpGuardMiddleware::allowed('203.0.113.5', '/unrestricted'));
    }

    public function testRateLimiterBlocksAfterBurstLimit(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([AddressRateLimitEntity::class]);
        $limiter = new AddressRateLimiterService(new AddressDoctrineRateLimitRepository($entityManager), 2, 1);

        self::assertTrue($limiter->check('client-1', 'address_lookup'));
        self::assertTrue($limiter->check('client-1', 'address_lookup'));
        self::assertTrue($limiter->check('client-1', 'address_lookup'));
        self::assertFalse($limiter->check('client-1', 'address_lookup'));
    }

    public function testRateLimiterResetsExpiredWindow(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([AddressRateLimitEntity::class]);
        $limiter = new AddressRateLimiterService(new AddressDoctrineRateLimitRepository($entityManager), 1, 0);

        self::assertTrue($limiter->check('client-1', 'address_lookup'));
        self::assertFalse($limiter->check('client-1', 'address_lookup'));

        $entity = $entityManager->find(AddressRateLimitEntity::class, ['client' => 'client-1', 'rkey' => 'address_lookup']);
        self::assertInstanceOf(AddressRateLimitEntity::class, $entity);
        $entity->setTs(time() - 61);
        $entityManager->flush();

        self::assertTrue($limiter->check('client-1', 'address_lookup'));
        self::assertSame(1, $entity->getCnt());
    }

    public function testRateLimiterKeepsClientAndOperationCountersIndependent(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([AddressRateLimitEntity::class]);
        $limiter = new AddressRateLimiterService(new AddressDoctrineRateLimitRepository($entityManager), 1, 0);

        self::assertTrue($limiter->check('client-1', 'address_lookup'));
        self::assertFalse($limiter->check('client-1', 'address_lookup'));

        self::assertTrue($limiter->check('client-1', 'address_write'));
        self::assertTrue($limiter->check('client-2', 'address_lookup'));
        self::assertFalse($limiter->check('client-1', 'address_lookup'));
    }

    public function testRateLimitEntityRoundTripsAllPersistenceFields(): void
    {
        $entity = (new AddressRateLimitEntity())
            ->setClient('client-direct')
            ->setRkey('address_direct')
            ->setTs(123456)
            ->setCnt(7);

        self::assertSame('client-direct', $entity->getClient());
        self::assertSame('address_direct', $entity->getRkey());
        self::assertSame(123456, $entity->getTs());
        self::assertSame(7, $entity->getCnt());
    }
}
