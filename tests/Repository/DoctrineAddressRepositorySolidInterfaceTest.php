<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Repository;

use App\Repository\Persistence\DoctrineAddressRepository;
use App\RepositoryInterface\Persistence\AddressEvidenceRepositoryInterface;
use App\RepositoryInterface\Persistence\AddressGovernanceRepositoryInterface;
use App\RepositoryInterface\Persistence\AddressOperationalRepositoryInterface;
use App\RepositoryInterface\Persistence\AddressPortfolioRepositoryInterface;
use App\RepositoryInterface\Persistence\AddressQueueRepositoryInterface;
use App\RepositoryInterface\Persistence\AddressReadRepositoryInterface;
use App\RepositoryInterface\Persistence\AddressWriteRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class DoctrineAddressRepositorySolidInterfaceTest extends TestCase
{
    public function testRepositoryExposesOnlyNarrowSolidContracts(): void
    {
        self::assertTrue(is_a(DoctrineAddressRepository::class, AddressWriteRepositoryInterface::class, true));
        self::assertTrue(is_a(DoctrineAddressRepository::class, AddressReadRepositoryInterface::class, true));
        self::assertTrue(is_a(DoctrineAddressRepository::class, AddressEvidenceRepositoryInterface::class, true));
        self::assertTrue(is_a(DoctrineAddressRepository::class, AddressOperationalRepositoryInterface::class, true));
        self::assertTrue(is_a(DoctrineAddressRepository::class, AddressQueueRepositoryInterface::class, true));
        self::assertTrue(is_a(DoctrineAddressRepository::class, AddressGovernanceRepositoryInterface::class, true));
        self::assertTrue(is_a(DoctrineAddressRepository::class, AddressPortfolioRepositoryInterface::class, true));
    }
}
