<?php

declare(strict_types=1);

namespace Tests\Repository;

use App\Addressing\Contract\AddressInterface;
use App\Addressing\Entity\AddressEntity;
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

    public function testOperationalAndEntityHelpersCoverRuntimeBranches(): void
    {
        self::assertNull($this->repository->stringFilterValue([], 'x'));
        self::assertNull($this->repository->stringFilterValue(['x' => '  '], 'x'));
        self::assertSame('value', $this->repository->stringFilterValue(['x' => ' value '], 'x'));
        self::assertTrue($this->repository->hasEvidenceFilterValue(['hasEvidence' => true]));
        self::assertFalse($this->repository->hasEvidenceFilterValue(['hasEvidence' => false]));
        self::assertNull($this->repository->hasEvidenceFilterValue(['hasEvidence' => 'yes']));

        $patch = $this->repository->normalizePatch('addr-1', 'canonical', [
            'revalidationDueAt' => '2026-12-01T00:00:00+00:00',
            'revalidationPolicy' => 'monthly',
            'lastValidationProvider' => 'provider-a',
            'lastValidationStatus' => 'validated',
            'lastValidationScore' => '91',
        ]);
        self::assertSame('monthly', $patch['revalidation_policy']);
        self::assertSame(91, $patch['last_validation_score']);

        $entity = (new AddressEntity())
            ->setId('addr-1')
            ->setGovernanceStatus('canonical')
            ->setValidationStatus('pending')
            ->setCreatedAt(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $updatedAt = new \DateTimeImmutable('2026-10-05T12:00:00+00:00');
        $this->repository->applyPatch($entity, $patch, $updatedAt);
        self::assertSame('monthly', $entity->getRevalidationPolicy());
        self::assertSame('provider-a', $entity->getLastValidationProvider());
        self::assertSame('validated', $entity->getLastValidationStatus());
        self::assertSame(91, $entity->getLastValidationScore());
        self::assertSame($updatedAt, $entity->getUpdatedAt());

        self::assertFalse($this->repository->hasEvidenceEntityValue($entity));
        $entity->setProviderDigest('digest');
        self::assertTrue($this->repository->hasEvidenceEntityValue($entity));

        self::assertNull($this->repository->entityCursor([], 1));
        self::assertSame('addr-1', $this->repository->entityCursor([$entity], 1));
        self::assertStringContainsString('rawInputSnapshot', $this->repository->evidenceClauseDqlValue('a', true));
        self::assertStringStartsWith('NOT ', $this->repository->evidenceClauseDqlValue('a', false));
        self::assertSame(12, $this->repository->intRowValueValue(['n' => '12'], 'n'));
        self::assertSame(0, $this->repository->intRowValueValue(['n' => 'bad'], 'n'));
        self::assertSame('42', $this->repository->stringValueValue(42));
        self::assertSame('', $this->repository->stringValueValue([]));
        self::assertSame('2026-10-05T12:00:00+00:00', $this->repository->nullableDateStringValue($updatedAt));
        self::assertSame('2026-10-05T12:00:00+00:00', $this->repository->nullableDateTimeValue('2026-10-05T12:00:00+00:00')?->format(DATE_ATOM));
    }

    public function testRemainingScopeCursorScalarAndEvidenceHelperBranches(): void
    {
        $this->repository->ensureScope('owner-1', null);
        $this->repository->ensureScope(null, 'vendor-1');

        try {
            $this->repository->ensureScope(null, null);
            self::fail('Expected missing tenant scope to throw.');
        } catch (\RuntimeException $exception) {
            self::assertSame('tenant_scope_required', $exception->getMessage());
        }

        try {
            $this->repository->ensureScope('owner-1', 'vendor-1');
            self::fail('Expected ambiguous tenant scope to throw.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('address_owner_vendor_scope_ambiguous', $exception->getMessage());
        }

        $params = [];
        self::assertSame(
            '2026-12-31T23:59:59+00:00',
            $this->repository->summaryDueBeforeValue($params, ['revalidationDueBefore' => ' 2026-12-31T23:59:59+00:00 ']),
        );
        self::assertSame('2026-12-31T23:59:59+00:00', $params['summary_due_before']);

        $defaultParams = [];
        self::assertNull($this->repository->summaryDueBeforeValue($defaultParams, []));
        self::assertIsString($defaultParams['summary_due_before'] ?? null);
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(DATE_ATOM, (string) $defaultParams['summary_due_before']));

        self::assertNull($this->repository->stringFilterValue(['x' => []], 'x'));
        self::assertSame('plain', $this->repository->stringValueValue('plain'));
        self::assertSame('1.5', $this->repository->stringValueValue(1.5));
        self::assertSame('1', $this->repository->stringValueValue(true));
        self::assertSame('plain-date', $this->repository->nullableDateStringValue('plain-date'));
        self::assertNull($this->repository->nullableDateTimeValue(null));

        self::assertNull($this->repository->pageCursor([], 0));
        $entity = (new AddressEntity())
            ->setId('cursor-1')
            ->setCreatedAt(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        self::assertNull($this->repository->entityCursor([], 0));
        self::assertNull($this->repository->entityCursor([$entity], 2));

        self::assertSame('erp|manual', $this->repository->sortKey(['sourceSystem' => 'erp', 'sourceType' => 'manual']));
        self::assertSame('provider-a|validated', $this->repository->sortKey(['validationProvider' => 'provider-a', 'validationStatus' => 'validated']));
        self::assertSame('v3|', $this->repository->sortKey(['normalizationVersion' => 'v3']));

        $evidenceEntities = [
            (new AddressEntity())->setRawInputSnapshot(['x' => 1]),
            (new AddressEntity())->setNormalizedSnapshot(['x' => 1]),
            (new AddressEntity())->setProviderDigest('digest'),
            (new AddressEntity())->setValidationRaw(['x' => 1]),
            (new AddressEntity())->setValidationVerdict(['x' => 1]),
        ];
        foreach ($evidenceEntities as $evidenceEntity) {
            self::assertTrue($this->repository->hasEvidenceEntityValue($evidenceEntity));
        }

        $timestamp = $this->repository->currentTimestampValue();
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(DATE_ATOM, $timestamp));
    }

    public function testGovernanceLinkHelperCoversEveryGovernanceStatus(): void
    {
        $links = [
            'duplicate' => 'duplicate-1',
            'superseded' => 'superseded-1',
            'alias' => 'alias-1',
            'conflict' => 'conflict-1',
            'canonical' => null,
        ];

        foreach ($links as $status => $expected) {
            $address = $this->createMock(AddressInterface::class);
            $address->method('governanceStatus')->willReturn($status);
            $address->method('duplicateOfId')->willReturn('duplicate-1');
            $address->method('supersededById')->willReturn('superseded-1');
            $address->method('aliasOfId')->willReturn('alias-1');
            $address->method('conflictWithId')->willReturn('conflict-1');

            self::assertSame($expected, $this->repository->governanceLinkValue($address));
        }
    }

    public function testGroupedPortfolioAccumulatesGovernanceEvidenceAndOperationalCounters(): void
    {
        $due = (new AddressEntity())
            ->setId('a')
            ->setCountryCode('US')
            ->setGovernanceStatus('duplicate')
            ->setValidationStatus('uncertain')
            ->setRevalidationDueAt(new \DateTimeImmutable('2020-01-01T00:00:00+00:00'))
            ->setProviderDigest('digest')
            ->setCreatedAt(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $clean = (new AddressEntity())
            ->setId('b')
            ->setCountryCode('US')
            ->setGovernanceStatus('canonical')
            ->setValidationStatus('validated')
            ->setCreatedAt(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));

        $rows = $this->repository->groupPortfolio([$due, $clean]);
        self::assertCount(1, $rows);
        self::assertSame(2, $rows[0]['total']);
        self::assertSame(1, $rows[0]['duplicate']);
        self::assertSame(1, $rows[0]['canonical']);
        self::assertSame(1, $rows[0]['evidenceBacked']);
        self::assertSame(1, $rows[0]['evidenceMissing']);
        self::assertSame(1, $rows[0]['dueForRevalidation']);
        self::assertSame(1, $rows[0]['uncertainValidation']);
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

    /** @param array<string, mixed> $filters */
    public function stringFilterValue(array $filters, string $key): ?string
    {
        return $this->stringFilter($filters, $key);
    }

    /** @param array<string, mixed> $filters */
    public function hasEvidenceFilterValue(array $filters): ?bool
    {
        return $this->hasEvidenceFilter($filters);
    }

    /**
     * @param array<string, mixed> $patch
     *
     * @return array<string, mixed>
     */
    public function normalizePatch(string $id, string $status, array $patch): array
    {
        return $this->normalizeOperationalPatch($id, $status, $patch);
    }

    /** @param array<string, mixed> $patch */
    public function applyPatch(AddressEntity $entity, array $patch, \DateTimeImmutable $updatedAt): void
    {
        $this->applyOperationalPatchToEntity($entity, $patch, $updatedAt);
    }

    public function hasEvidenceEntityValue(AddressEntity $entity): bool
    {
        return $this->hasEvidenceEntity($entity);
    }

    /** @param list<AddressEntity> $entities */
    public function entityCursor(array $entities, int $limit): ?string
    {
        return $this->pageCursorFromEntities($entities, $limit);
    }

    public function evidenceClauseDqlValue(string $alias, bool $hasEvidence): string
    {
        return $this->evidencePresenceClauseDql($alias, $hasEvidence);
    }

    /** @param array<string, mixed> $row */
    public function intRowValueValue(array $row, string $key): int
    {
        return $this->intRowValue($row, $key);
    }

    public function stringValueValue(mixed $value): string
    {
        return $this->stringValue($value);
    }

    public function nullableDateStringValue(mixed $value): ?string
    {
        return $this->nullableDateString($value);
    }

    public function nullableDateTimeValue(mixed $value): ?\DateTimeImmutable
    {
        return $this->nullableDateTime($value);
    }

    public function ensureScope(?string $ownerId, ?string $vendorId): void
    {
        $this->ensureTenantScope($ownerId, $vendorId);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $filters
     */
    public function summaryDueBeforeValue(array &$params, array $filters): ?string
    {
        return $this->summaryDueBefore($params, $filters);
    }

    public function governanceLinkValue(AddressInterface $address): ?string
    {
        return $this->governanceLinkId($address);
    }

    public function currentTimestampValue(): string
    {
        return $this->currentTimestampAtom();
    }

    /**
     * @param list<AddressEntity> $entities
     *
     * @return list<array<string, mixed>>
     */
    public function groupPortfolio(array $entities): array
    {
        return $this->buildGroupedPortfolio(
            $entities,
            static fn (AddressEntity $entity): string => $entity->getCountryCode(),
            static fn (AddressEntity $entity): array => ['countryCode' => $entity->getCountryCode()],
        );
    }
}
