<?php

declare(strict_types=1);

namespace Tests\Repository;

use App\Addressing\Factory\AddressEntityMapper;
use App\Addressing\Repository\AddressAbstractDoctrineRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class AddressAbstractDoctrineRepositoryTest extends TestCase
{
    private AddressAbstractDoctrineRepositoryProbe $repository;

    protected function setUp(): void
    {
        $this->repository = new AddressAbstractDoctrineRepositoryProbe(
            $this->createMock(EntityManagerInterface::class),
            new AddressEntityMapper(),
        );
    }

    public function testTenantWhereBuildsOwnerVendorAndUnscopedPredicates(): void
    {
        $params = [];
        self::assertSame('owner_id = :owner_id', $this->repository->tenantWhere('owner-1', null, $params));
        self::assertSame(['owner_id' => 'owner-1'], $params);

        $params = [];
        self::assertSame('vendor_id = :vendor_id', $this->repository->tenantWhere(null, 'vendor-1', $params));
        self::assertSame(['vendor_id' => 'vendor-1'], $params);

        $params = [];
        self::assertSame('(owner_id IS NULL AND vendor_id IS NULL)', $this->repository->tenantWhere(null, null, $params));
        self::assertSame([], $params);
    }

    public function testTenantWhereRejectsAmbiguousScope(): void
    {
        $params = [];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('address_owner_vendor_scope_ambiguous');
        $this->repository->tenantWhere('owner-1', 'vendor-1', $params);
    }

    public function testScopedSearchWhereAddsOptionalCountryAndQuery(): void
    {
        $params = [];
        $where = $this->repository->scopedSearchWhere('owner-1', null, 'US', ' Houston ', $params);

        self::assertSame([
            'deleted_at IS NULL',
            'owner_id = :owner_id',
            'country_code = :country_code',
            "LOWER(line1 || ' ' || city || ' ' || COALESCE(postal_code, '')) LIKE :q",
        ], $where);
        self::assertSame([
            'owner_id' => 'owner-1',
            'country_code' => 'US',
            'q' => '%houston%',
        ], $params);
    }

    public function testScalarNormalizersHandleSupportedAndUnsupportedValues(): void
    {
        self::assertNull($this->repository->nullableStringValue(null));
        self::assertSame('42', $this->repository->nullableStringValue(42));
        self::assertSame('1', $this->repository->nullableStringValue(true));
        self::assertNull($this->repository->nullableStringValue('   '));
        self::assertNull($this->repository->nullableStringValue([]));

        self::assertSame(12, $this->repository->nullableIntValue(12));
        self::assertSame(12, $this->repository->nullableIntValue(12.8));
        self::assertSame(12, $this->repository->nullableIntValue('12'));
        self::assertNull($this->repository->nullableIntValue('nope'));

        self::assertSame(12.5, $this->repository->nullableFloatValue(12.5));
        self::assertSame(12.0, $this->repository->nullableFloatValue(12));
        self::assertSame(12.5, $this->repository->nullableFloatValue('12.5'));
        self::assertNull($this->repository->nullableFloatValue(false));

        self::assertTrue($this->repository->nullableBoolValue(true));
        self::assertTrue($this->repository->nullableBoolValue(1));
        self::assertFalse($this->repository->nullableBoolValue(0));
        self::assertTrue($this->repository->nullableBoolValue('YES'));
        self::assertFalse($this->repository->nullableBoolValue('false'));
        self::assertNull($this->repository->nullableBoolValue('unknown'));
    }

    public function testJsonAndCursorHelpersCoverValidAndInvalidInputs(): void
    {
        self::assertSame(['a' => 1], $this->repository->nullableJsonValue(['a' => 1]));
        self::assertSame(['a' => 1], $this->repository->nullableJsonValue('{"a":1}'));
        self::assertNull($this->repository->nullableJsonValue('{invalid'));
        self::assertNull($this->repository->nullableJsonValue(null));

        $createdAt = '2026-10-05T12:00:00+00:00';
        $cursor = $this->repository->encodeCursor($createdAt, 'snapshot-1');
        self::assertSame([$createdAt, 'snapshot-1'], $this->repository->decodeCursor($cursor));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_evidence_cursor');
        $this->repository->decodeCursor('not-base64');
    }

    public function testPagingEvidenceAndPortfolioHelpersAreDeterministic(): void
    {
        self::assertNull($this->repository->pageCursor([], 10));
        self::assertNull($this->repository->pageCursor([['id' => 'a']], 2));
        self::assertSame('b', $this->repository->pageCursor([['id' => 'a'], ['id' => 'b']], 2));
        self::assertNull($this->repository->pageCursor([['id' => 'a'], ['id' => 2]], 2));

        self::assertSame(
            '(raw_input_snapshot IS NOT NULL OR normalized_snapshot IS NOT NULL OR provider_digest IS NOT NULL OR validation_raw IS NOT NULL OR validation_verdict IS NOT NULL)',
            $this->repository->evidenceClause(true),
        );
        self::assertStringStartsWith('NOT ', $this->repository->evidenceClause(false));

        self::assertSame('US|manual', $this->repository->sortKey(['countryCode' => 'US', 'sourceType' => 'manual']));
        self::assertSame('|', $this->repository->sortKey(['countryCode' => []]));
    }
}

final readonly class AddressAbstractDoctrineRepositoryProbe extends AddressAbstractDoctrineRepository
{
    /** @param array<string, mixed> $params */
    public function tenantWhere(?string $ownerId, ?string $vendorId, array &$params): string
    {
        return $this->buildTenantWhere($ownerId, $vendorId, $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<string>
     */
    public function scopedSearchWhere(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $query, array &$params): array
    {
        return $this->buildScopedSearchWhere($ownerId, $vendorId, $countryCode, $query, $params);
    }

    public function nullableStringValue(mixed $value): ?string
    {
        return $this->nullableString($value);
    }
    public function nullableIntValue(mixed $value): ?int
    {
        return $this->nullableInt($value);
    }
    public function nullableFloatValue(mixed $value): ?float
    {
        return $this->nullableFloat($value);
    }
    public function nullableBoolValue(mixed $value): ?bool
    {
        return $this->nullableBool($value);
    }

    /** @return array<string, mixed>|null */
    public function nullableJsonValue(mixed $value): ?array
    {
        return $this->nullableJsonArray($value);
    }

    /** @param list<array<string, mixed>> $rows */
    public function pageCursor(array $rows, int $limit): ?string
    {
        return $this->pageCursorFromRows($rows, $limit);
    }
    public function evidenceClause(bool $hasEvidence): string
    {
        return $this->evidencePresenceClause($hasEvidence);
    }

    /** @param array<string, mixed> $row */
    public function sortKey(array $row): string
    {
        return $this->portfolioSortKey($row);
    }
    public function encodeCursor(string $createdAt, string $id): string
    {
        return $this->encodeEvidenceCursor($createdAt, $id);
    }

    /** @return array{string, string} */
    public function decodeCursor(string $cursor): array
    {
        return $this->decodeEvidenceCursor($cursor);
    }
}
