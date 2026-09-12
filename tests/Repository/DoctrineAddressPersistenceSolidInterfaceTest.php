<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
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
