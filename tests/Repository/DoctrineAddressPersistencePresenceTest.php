<?php

declare(strict_types=1);

namespace Tests\Repository;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\Factory\AddressEntityMapper;
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
use Tests\Support\TestDatabase;

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

    public function testOperationalQueueSummaryCountsScopedReviewAndRevalidationWork(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([AddressEntity::class]);
        $repository = new AddressDoctrineQueueRepository($entityManager, new AddressEntityMapper());

        foreach ([
            ['due-conflict', 'owner-1', 'verified', null, 'conflict', '2026-01-01T00:00:00+00:00', 'v1', 'digest-1'],
            ['uncertain-duplicate', 'owner-1', 'uncertain', null, 'duplicate', '2027-01-01T00:00:00+00:00', 'v2', null],
            ['stale-missing', 'owner-1', 'verified', null, 'canonical', null, null, null],
            ['other-tenant', 'owner-2', 'uncertain', null, 'conflict', '2026-01-01T00:00:00+00:00', 'v1', null],
        ] as [$id, $ownerId, $validationStatus, $lastValidationStatus, $governanceStatus, $revalidationDueAt, $normalizationVersion, $providerDigest]) {
            $entity = (new AddressEntity())
                ->setId($id)
                ->setOwnerId($ownerId)
                ->setLine1('123 Main St')
                ->setCity('Houston')
                ->setCountryCode('US')
                ->setValidationStatus($validationStatus)
                ->setLastValidationStatus($lastValidationStatus)
                ->setGovernanceStatus($governanceStatus)
                ->setNormalizationVersion($normalizationVersion)
                ->setProviderDigest($providerDigest)
                ->setCreatedAt(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));

            if (null !== $revalidationDueAt) {
                $entity->setRevalidationDueAt(new \DateTimeImmutable($revalidationDueAt));
            }

            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $summary = $repository->summarizeOperationalQueues('owner-1', null, null, null, [
            'revalidationDueBefore' => '2026-06-01T00:00:00+00:00',
        ]);

        self::assertSame(3, $summary['total']);
        self::assertSame(1, $summary['dueForRevalidation']);
        self::assertSame(2, $summary['evidenceMissing']);
        self::assertSame(1, $summary['uncertainValidation']);
        self::assertSame(1, $summary['conflictReview']);
        self::assertSame(1, $summary['duplicateReview']);
        self::assertSame(0, $summary['staleNormalizationVersion']);

        $staleSummary = $repository->summarizeOperationalQueues('owner-1', null, null, null, [
            'expectedNormalizationVersion' => 'v2',
        ]);

        self::assertSame(2, $staleSummary['total']);
        self::assertSame(2, $staleSummary['staleNormalizationVersion']);
    }
}
