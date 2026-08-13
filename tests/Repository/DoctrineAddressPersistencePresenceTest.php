<?php

declare(strict_types=1);

namespace Tests\Repository;

use App\Addressing\Repository\Persistence\DoctrineAddressEvidenceRepository;
use App\Addressing\Repository\Persistence\DoctrineAddressGovernanceRepository;
use App\Addressing\Repository\Persistence\DoctrineAddressOperationalRepository;
use App\Addressing\Repository\Persistence\DoctrineAddressPortfolioRepository;
use App\Addressing\Repository\Persistence\DoctrineAddressQueueRepository;
use App\Addressing\Repository\Persistence\DoctrineAddressReadRepository;
use App\Addressing\Repository\Persistence\DoctrineAddressWriteRepository;
use App\Addressing\RepositoryInterface\Persistence\AddressEvidenceRepositoryInterface;
use App\Addressing\RepositoryInterface\Persistence\AddressGovernanceRepositoryInterface;
use App\Addressing\RepositoryInterface\Persistence\AddressOperationalRepositoryInterface;
use App\Addressing\RepositoryInterface\Persistence\AddressPortfolioRepositoryInterface;
use App\Addressing\RepositoryInterface\Persistence\AddressQueueRepositoryInterface;
use App\Addressing\RepositoryInterface\Persistence\AddressReadRepositoryInterface;
use App\Addressing\RepositoryInterface\Persistence\AddressWriteRepositoryInterface;
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
