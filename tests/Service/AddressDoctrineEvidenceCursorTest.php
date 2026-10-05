<?php

declare(strict_types=1);

namespace Tests\Service;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\Entity\AddressEvidenceSnapshotEntity;
use App\Addressing\Factory\AddressEntityMapper;
use App\Addressing\Repository\AddressDoctrineEvidenceRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class AddressDoctrineEvidenceCursorTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function invalidCursorProvider(): iterable
    {
        yield 'not base64' => ['%%%'];
        yield 'missing separator' => [base64_encode('2026-10-04T10:00:00+00:00')];
        yield 'missing timestamp' => [base64_encode("\nsnapshot-id")];
        yield 'invalid timestamp' => [base64_encode("not-a-date\nsnapshot-id")];
        yield 'missing snapshot id' => [base64_encode("2026-10-04T10:00:00+00:00\n")];
        yield 'multiline snapshot id' => [base64_encode("2026-10-04T10:00:00+00:00\nsnapshot\nid")];
    }

    #[DataProvider('invalidCursorProvider')]
    public function testEvidenceHistoryRejectsMalformedCursor(string $cursor): void
    {
        $repository = $this->repository();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_evidence_cursor');

        $repository->findEvidenceHistoryPage('address-1', 'owner-1', null, 10, $cursor);
    }

    public function testEvidenceHistoryAcceptsCanonicalCursorShape(): void
    {
        $repository = $this->repository();
        $cursor = base64_encode("2026-10-04T10:00:00+00:00\nsnapshot-id");

        self::assertSame(
            ['items' => [], 'nextCursor' => null],
            $repository->findEvidenceHistoryPage('address-1', 'owner-1', null, 10, $cursor),
        );
    }

    public function testEvidenceRepositoryPersistsLatestAndPaginatedHistory(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([
            AddressEntity::class,
            AddressEvidenceSnapshotEntity::class,
        ]);
        $mapper = new AddressEntityMapper();
        $repository = new AddressDoctrineEvidenceRepository($entityManager, $mapper);

        $address = (new AddressEntity())
            ->setId('address-evidence-1')
            ->setOwnerId('owner-1')
            ->setLine1('123 Main St')
            ->setCity('Houston')
            ->setCountryCode('US')
            ->setValidationStatus('validated')
            ->setValidationProvider('provider-a')
            ->setValidatedAt(new \DateTimeImmutable('2026-10-04T10:00:00+00:00'))
            ->setSourceSystem('validator-suite')
            ->setSourceType('validator')
            ->setSourceReference('run-1')
            ->setNormalizationVersion('v1')
            ->setRawInputSnapshot(['line1' => '123 Main St'])
            ->setNormalizedSnapshot(['line1Norm' => '123 MAIN ST'])
            ->setValidationVerdict(['deliverable' => true])
            ->setProviderDigest('digest-1')
            ->setCreatedAt(new \DateTimeImmutable('2026-10-04T09:00:00+00:00'));
        $entityManager->persist($address);

        $withoutEvidence = (new AddressEntity())
            ->setId('address-no-evidence')
            ->setOwnerId('owner-1')
            ->setLine1('456 Main St')
            ->setCity('Houston')
            ->setCountryCode('US')
            ->setValidationStatus('pending')
            ->setCreatedAt(new \DateTimeImmutable('2026-10-04T09:00:00+00:00'));
        $entityManager->persist($withoutEvidence);
        $entityManager->flush();

        self::assertNull($repository->appendEvidenceSnapshot($mapper->fromDoctrine($withoutEvidence)));

        $first = $repository->appendEvidenceSnapshot($mapper->fromDoctrine($address));
        self::assertNotNull($first);
        self::assertSame('address-evidence-1', $first->addressId());
        self::assertSame('provider-a', $first->validatedBy());
        self::assertSame('digest-1', $first->providerDigest());

        $address
            ->setValidatedAt(new \DateTimeImmutable('2026-10-04T11:00:00+00:00'))
            ->setProviderDigest('digest-2')
            ->setSourceReference('run-2');
        $entityManager->flush();

        $second = $repository->appendEvidenceSnapshot($mapper->fromDoctrine($address));
        self::assertNotNull($second);
        self::assertSame('digest-2', $second->providerDigest());

        $latest = $repository->getLatestEvidenceSnapshot('address-evidence-1', 'owner-1', null);
        self::assertNotNull($latest);
        self::assertSame('digest-2', $latest->providerDigest());
        self::assertNull($repository->getLatestEvidenceSnapshot('address-evidence-1', 'owner-2', null));

        $firstPage = $repository->findEvidenceHistoryPage('address-evidence-1', 'owner-1', null, 1, null);
        self::assertCount(1, $firstPage['items']);
        self::assertNotNull($firstPage['nextCursor']);

        $secondPage = $repository->findEvidenceHistoryPage(
            'address-evidence-1',
            'owner-1',
            null,
            10,
            $firstPage['nextCursor'],
        );
        self::assertCount(1, $secondPage['items']);
        self::assertNull($secondPage['nextCursor']);
    }

    private function repository(): AddressDoctrineEvidenceRepository
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([
            AddressEntity::class,
            AddressEvidenceSnapshotEntity::class,
        ]);

        return new AddressDoctrineEvidenceRepository($entityManager, new AddressEntityMapper());
    }
}
