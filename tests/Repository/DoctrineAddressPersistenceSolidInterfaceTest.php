<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
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
