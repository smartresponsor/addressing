<?php

declare(strict_types=1);

namespace Tests\Repository;

use App\Repository\Persistence\DoctrineAddressEvidenceRepository;
use App\Repository\Persistence\DoctrineAddressGovernanceRepository;
use App\Repository\Persistence\DoctrineAddressOperationalRepository;
use App\Repository\Persistence\DoctrineAddressPortfolioRepository;
use App\Repository\Persistence\DoctrineAddressQueueRepository;
use App\Repository\Persistence\DoctrineAddressReadRepository;
use App\Repository\Persistence\DoctrineAddressWriteRepository;
use App\RepositoryInterface\Persistence\AddressEvidenceRepositoryInterface;
use App\RepositoryInterface\Persistence\AddressGovernanceRepositoryInterface;
use App\RepositoryInterface\Persistence\AddressOperationalRepositoryInterface;
use App\RepositoryInterface\Persistence\AddressPortfolioRepositoryInterface;
use App\RepositoryInterface\Persistence\AddressQueueRepositoryInterface;
use App\RepositoryInterface\Persistence\AddressReadRepositoryInterface;
use App\RepositoryInterface\Persistence\AddressWriteRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class DoctrineAddressPersistencePresenceTest extends TestCase
{
    public function testDoctrineRepositoriesImplementNarrowPersistenceContractsOnly(): void
    {
        self::assertTrue(is_a(DoctrineAddressWriteRepository::class, AddressWriteRepositoryInterface::class, true));
        self::assertTrue(is_a(DoctrineAddressReadRepository::class, AddressReadRepositoryInterface::class, true));
        self::assertTrue(is_a(DoctrineAddressEvidenceRepository::class, AddressEvidenceRepositoryInterface::class, true));
        self::assertTrue(is_a(DoctrineAddressOperationalRepository::class, AddressOperationalRepositoryInterface::class, true));
        self::assertTrue(is_a(DoctrineAddressQueueRepository::class, AddressQueueRepositoryInterface::class, true));
        self::assertTrue(is_a(DoctrineAddressGovernanceRepository::class, AddressGovernanceRepositoryInterface::class, true));
        self::assertTrue(is_a(DoctrineAddressPortfolioRepository::class, AddressPortfolioRepositoryInterface::class, true));
    }
}
