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

    private function repository(): AddressDoctrineEvidenceRepository
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([
            AddressEntity::class,
            AddressEvidenceSnapshotEntity::class,
        ]);

        return new AddressDoctrineEvidenceRepository($entityManager, new AddressEntityMapper());
    }
}
