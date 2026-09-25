<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
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

final class DoctrineAddressPersistenceSolidInterfaceTest extends TestCase
{
    public function testRepositoryImplementationsExposeOnlyTheirNarrowSolidContracts(): void
    {
        self::assertSame([AddressWriteRepositoryInterface::class], array_values(class_implements(AddressDoctrineWriteRepository::class)));
        self::assertSame([AddressReadRepositoryInterface::class], array_values(class_implements(AddressDoctrineReadRepository::class)));
        self::assertSame([AddressEvidenceRepositoryInterface::class], array_values(class_implements(AddressDoctrineEvidenceRepository::class)));
        self::assertSame([AddressOperationalRepositoryInterface::class], array_values(class_implements(AddressDoctrineOperationalRepository::class)));
        self::assertSame([AddressQueueRepositoryInterface::class], array_values(class_implements(AddressDoctrineQueueRepository::class)));
        self::assertSame([AddressGovernanceRepositoryInterface::class], array_values(class_implements(AddressDoctrineGovernanceRepository::class)));
        self::assertSame([AddressPortfolioRepositoryInterface::class], array_values(class_implements(AddressDoctrinePortfolioRepository::class)));
    }
}
