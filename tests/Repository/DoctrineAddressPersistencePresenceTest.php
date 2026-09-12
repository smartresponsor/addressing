<?php

declare(strict_types=1);

namespace Tests\Repository;

use App\Addressing\Repository\DoctrineAddressEvidenceRepository;
use App\Addressing\Repository\DoctrineAddressGovernanceRepository;
use App\Addressing\Repository\DoctrineAddressOperationalRepository;
use App\Addressing\Repository\DoctrineAddressPortfolioRepository;
use App\Addressing\Repository\DoctrineAddressQueueRepository;
use App\Addressing\Repository\DoctrineAddressReadRepository;
use App\Addressing\Repository\DoctrineAddressWriteRepository;
use App\Addressing\RepositoryInterface\AddressEvidenceRepositoryInterface;
use App\Addressing\RepositoryInterface\AddressGovernanceRepositoryInterface;
use App\Addressing\RepositoryInterface\AddressOperationalRepositoryInterface;
use App\Addressing\RepositoryInterface\AddressPortfolioRepositoryInterface;
use App\Addressing\RepositoryInterface\AddressQueueRepositoryInterface;
use App\Addressing\RepositoryInterface\AddressReadRepositoryInterface;
use App\Addressing\RepositoryInterface\AddressWriteRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class DoctrineAddressPersistencePresenceTest extends TestCase
{
    public function testDoctrineRepositoriesImplementNarrowPersistenceContractsOnly(): void
    {
        self::assertContains(AddressWriteRepositoryInterface::class, class_implements(DoctrineAddressWriteRepository::class));
        self::assertContains(AddressReadRepositoryInterface::class, class_implements(DoctrineAddressReadRepository::class));
        self::assertContains(AddressEvidenceRepositoryInterface::class, class_implements(DoctrineAddressEvidenceRepository::class));
        self::assertContains(AddressOperationalRepositoryInterface::class, class_implements(DoctrineAddressOperationalRepository::class));
        self::assertContains(AddressQueueRepositoryInterface::class, class_implements(DoctrineAddressQueueRepository::class));
        self::assertContains(AddressGovernanceRepositoryInterface::class, class_implements(DoctrineAddressGovernanceRepository::class));
        self::assertContains(AddressPortfolioRepositoryInterface::class, class_implements(DoctrineAddressPortfolioRepository::class));
    }
}
