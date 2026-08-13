<?php

declare(strict_types=1);

namespace Tests\Repository;

use App\Addressing\Repository\Persistence\DoctrineAddressRepository;
use App\Addressing\RepositoryInterface\Persistence\AddressEvidenceRepositoryInterface;
use App\Addressing\RepositoryInterface\Persistence\AddressGovernanceRepositoryInterface;
use App\Addressing\RepositoryInterface\Persistence\AddressOperationalRepositoryInterface;
use App\Addressing\RepositoryInterface\Persistence\AddressPortfolioRepositoryInterface;
use App\Addressing\RepositoryInterface\Persistence\AddressQueueRepositoryInterface;
use App\Addressing\RepositoryInterface\Persistence\AddressReadRepositoryInterface;
use App\Addressing\RepositoryInterface\Persistence\AddressWriteRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class DoctrineAddressRepositoryPresenceTest extends TestCase
{
    public function testDoctrineRepositoryImplementsNarrowPersistenceContracts(): void
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
