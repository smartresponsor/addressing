<?php

declare(strict_types=1);

namespace Tests\Repository;

use App\Addressing\Repository\AddressDoctrineEvidenceRepository;
use App\Addressing\Repository\AddressDoctrineGovernanceRepository;
use App\Addressing\Repository\AddressDoctrineOperationalRepository;
use App\Addressing\Repository\AddressDoctrinePortfolioRepository;
use App\Addressing\Repository\AddressDoctrineQueueRepository;
use App\Addressing\Repository\AddressDoctrineReadRepository;
use App\Addressing\Repository\AddressDoctrineWriteRepository;
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
        self::assertContains(AddressWriteRepositoryInterface::class, class_implements(AddressDoctrineWriteRepository::class));
        self::assertContains(AddressReadRepositoryInterface::class, class_implements(AddressDoctrineReadRepository::class));
        self::assertContains(AddressEvidenceRepositoryInterface::class, class_implements(AddressDoctrineEvidenceRepository::class));
        self::assertContains(AddressOperationalRepositoryInterface::class, class_implements(AddressDoctrineOperationalRepository::class));
        self::assertContains(AddressQueueRepositoryInterface::class, class_implements(AddressDoctrineQueueRepository::class));
        self::assertContains(AddressGovernanceRepositoryInterface::class, class_implements(AddressDoctrineGovernanceRepository::class));
        self::assertContains(AddressPortfolioRepositoryInterface::class, class_implements(AddressDoctrinePortfolioRepository::class));
    }
}
