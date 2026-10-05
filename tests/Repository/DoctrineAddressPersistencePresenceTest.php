<?php

declare(strict_types=1);

namespace Tests\Repository;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\Entity\AddressOutboxEntity;
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
use App\Addressing\Value\Persistence\AddressPageCriteria;
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

    public function testOperationalRepositoryPersistsScopedPatchAndOutboxEvent(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([
            AddressEntity::class,
            AddressOutboxEntity::class,
        ]);
        $repository = new AddressDoctrineOperationalRepository($entityManager, new AddressEntityMapper());

        $entityManager->persist((new AddressEntity())
            ->setId('addr-op-1')
            ->setOwnerId('owner-1')
            ->setLine1('123 Main St')
            ->setCity('Houston')
            ->setCountryCode('US')
            ->setValidationStatus('pending')
            ->setGovernanceStatus('canonical')
            ->setCreatedAt(new \DateTimeImmutable('2026-01-01T00:00:00+00:00')));
        $entityManager->flush();

        self::assertFalse($repository->patchOperational('missing', 'owner-1', null, ['revalidationPolicy' => 'quarterly']));
        self::assertFalse($repository->patchOperational('addr-op-1', 'owner-1', null, []));
        self::assertTrue($repository->patchOperational('addr-op-1', 'owner-1', null, [
            'revalidationDueAt' => '2026-12-01T00:00:00+00:00',
            'revalidationPolicy' => 'quarterly',
            'lastValidationProvider' => 'provider-1',
            'lastValidationStatus' => 'validated',
            'lastValidationScore' => 91,
        ]));

        $updated = $entityManager->find(AddressEntity::class, 'addr-op-1');
        self::assertInstanceOf(AddressEntity::class, $updated);
        self::assertSame('quarterly', $updated->getRevalidationPolicy());
        self::assertSame('validated', $updated->getLastValidationStatus());
        self::assertSame(91, $updated->getLastValidationScore());
        self::assertNotNull($updated->getRevalidationDueAt());

        /** @var list<AddressOutboxEntity> $outboxRows */
        $outboxRows = $entityManager->getRepository(AddressOutboxEntity::class)->findAll();
        self::assertCount(1, $outboxRows);
        self::assertSame('AddressOperationalPatched', $outboxRows[0]->getEventName());
        $payload = json_decode($outboxRows[0]->getPayload(), true);
        self::assertIsArray($payload);
        self::assertSame('addr-op-1', $payload['id'] ?? null);
        self::assertSame('quarterly', $payload['revalidationPolicy'] ?? null);
        self::assertSame('validated', $payload['lastValidationStatus'] ?? null);
    }

    public function testOperationalRepositoryRequiresTenantScope(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([AddressEntity::class, AddressOutboxEntity::class]);
        $repository = new AddressDoctrineOperationalRepository($entityManager, new AddressEntityMapper());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenant_scope_required');
        $repository->patchOperational('addr-op-1', null, null, ['revalidationPolicy' => 'quarterly']);
    }

    public function testReadRepositoryCoversScopedLookupDedupeAndCursorPagination(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([AddressEntity::class]);
        $repository = new AddressDoctrineReadRepository($entityManager, new AddressEntityMapper());

        foreach ([
            ['a-1', 'owner-1', 'dedupe-1', '123 Main St'],
            ['a-2', 'owner-1', 'dedupe-2', '456 Main St'],
            ['b-1', 'owner-2', 'dedupe-3', '789 Main St'],
        ] as [$id, $ownerId, $dedupeKey, $line1]) {
            $entityManager->persist((new AddressEntity())
                ->setId($id)
                ->setOwnerId($ownerId)
                ->setLine1($line1)
                ->setCity('Houston')
                ->setCountryCode('US')
                ->setValidationStatus('pending')
                ->setDedupeKey($dedupeKey)
                ->setCreatedAt(new \DateTimeImmutable('2026-01-01T00:00:00+00:00')));
        }
        $entityManager->flush();

        self::assertSame('a-1', $repository->get('a-1', 'owner-1', null)?->id());
        self::assertNull($repository->get('a-1', 'owner-2', null));
        self::assertNull($repository->findByDedupeKey('   '));
        self::assertSame('a-1', $repository->findByDedupeKey(' dedupe-1 ')?->id());

        $firstPage = $repository->findPage(
            AddressPageCriteria::forScope('owner-1', null, 'US', 'Main')->withPagination(1, null),
        );
        self::assertCount(1, $firstPage['items']);
        self::assertSame('a-1', $firstPage['items'][0]->id());
        self::assertSame('a-1', $firstPage['nextCursor']);

        $secondPage = $repository->findPage(
            AddressPageCriteria::forScope('owner-1', null, 'US', 'Main')->withPagination(2, 'a-1'),
        );
        self::assertCount(1, $secondPage['items']);
        self::assertSame('a-2', $secondPage['items'][0]->id());
        self::assertNull($secondPage['nextCursor']);
    }
}
