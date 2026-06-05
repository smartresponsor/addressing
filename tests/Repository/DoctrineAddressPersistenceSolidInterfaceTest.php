<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
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

final class DoctrineAddressPersistenceSolidInterfaceTest extends TestCase
{
    public function testRepositoryImplementationsExposeOnlyTheirNarrowSolidContracts(): void
    {
        self::assertSame([AddressWriteRepositoryInterface::class], array_values(class_implements(DoctrineAddressWriteRepository::class)));
        self::assertSame([AddressReadRepositoryInterface::class], array_values(class_implements(DoctrineAddressReadRepository::class)));
        self::assertSame([AddressEvidenceRepositoryInterface::class], array_values(class_implements(DoctrineAddressEvidenceRepository::class)));
        self::assertSame([AddressOperationalRepositoryInterface::class], array_values(class_implements(DoctrineAddressOperationalRepository::class)));
        self::assertSame([AddressQueueRepositoryInterface::class], array_values(class_implements(DoctrineAddressQueueRepository::class)));
        self::assertSame([AddressGovernanceRepositoryInterface::class], array_values(class_implements(DoctrineAddressGovernanceRepository::class)));
        self::assertSame([AddressPortfolioRepositoryInterface::class], array_values(class_implements(DoctrineAddressPortfolioRepository::class)));
    }
}
