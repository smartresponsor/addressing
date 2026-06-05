<?php

declare(strict_types=1);

namespace App\Repository\Persistence;

use App\Contract\Message\AddressOutboxEventMessage;
use App\Contract\Message\AddressRecordPolicy;
use App\Doctrine\AddressEntityMapper;
use App\Entity\AddressEntity;
use App\Entity\AddressEvidenceSnapshotEntity;
use App\Entity\AddressOutboxEntity;
use App\Entity\Record\AddressData;
use App\Entity\Record\AddressEvidenceSnapshotData;
use App\EntityInterface\Record\AddressEvidenceSnapshotInterface;
use App\EntityInterface\Record\AddressInterface;
use App\Service\Application\AddressGovernancePolicy;
use Doctrine\ORM\EntityManagerInterface;

abstract readonly class AbstractDoctrineAddressRepository
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected AddressEntityMapper $mapper,
    ) {
    }

    protected function findDoctrineAddress(string $id, ?string $ownerId, ?string $vendorId): ?AddressEntity
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(AddressEntity::class, 'a')
            ->where('a.id = :id')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('id', $id)
            ->setMaxResults(1);
        $this->applyTenantScope($qb, 'a', $ownerId, $vendorId);

        $entity = $qb->getQuery()->getOneOrNullResult();

        return $entity instanceof AddressEntity ? $entity : null;
    }

    protected function applyTenantScope(\Doctrine\ORM\QueryBuilder $qb, string $alias, ?string $ownerId, ?string $vendorId): void
    {
        if (null !== $ownerId && null !== $vendorId) {
            throw new \InvalidArgumentException('address_owner_vendor_scope_ambiguous');
        }

        if (null !== $ownerId) {
            $qb->andWhere(sprintf('%s.ownerId = :ownerId', $alias))->setParameter('ownerId', $ownerId);

            return;
        }

        if (null !== $vendorId) {
            $qb->andWhere(sprintf('%s.vendorId = :vendorId', $alias))->setParameter('vendorId', $vendorId);
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<string>
     */
    protected function buildScopedSearchWhere(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $query, array &$params): array
    {
        $where = ['deleted_at IS NULL', $this->buildTenantWhere($ownerId, $vendorId, $params)];

        if (null !== $countryCode && '' !== $countryCode) {
            $where[] = 'country_code = :country_code';
            $params['country_code'] = $countryCode;
        }

        $query = null !== $query ? trim($query) : null;
        if (null !== $query && '' !== $query) {
            $where[] = "LOWER(line1 || ' ' || city || ' ' || COALESCE(postal_code, '')) LIKE :q";
            $params['q'] = '%'.mb_strtolower($query).'%';
        }

        return $where;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function buildTenantWhere(?string $ownerId, ?string $vendorId, array &$params): string
    {
        if (null !== $ownerId && null !== $vendorId) {
            throw new \InvalidArgumentException('address_owner_vendor_scope_ambiguous');
        }

        if (null !== $ownerId) {
            $params['owner_id'] = $ownerId;

            return 'owner_id = :owner_id';
        }

        if (null !== $vendorId) {
            $params['vendor_id'] = $vendorId;

            return 'vendor_id = :vendor_id';
        }

        return '(owner_id IS NULL AND vendor_id IS NULL)';
    }

    /**
     * @param list<string>         $where
     * @param array<string, mixed> $params
     * @param array<string, mixed> $filters
     */
    protected function applyPortfolioFilters(array &$where, array &$params, array $filters, bool $includeSourceSystem = false, bool $includeValidation = false): void
    {
        $sourceType = AddressRecordPolicy::normalizeSourceType($this->stringFilter($filters, 'sourceType'));
        if (null !== $sourceType) {
            $where[] = 'source_type = :source_type';
            $params['source_type'] = $sourceType;
        }

        if ($includeSourceSystem) {
            $sourceSystem = $this->stringFilter($filters, 'sourceSystem');
            if (null !== $sourceSystem) {
                $where[] = 'source_system = :source_system';
                $params['source_system'] = $sourceSystem;
            }
        }

        if ($includeValidation) {
            $validationProvider = $this->stringFilter($filters, 'validationProvider');
            if (null !== $validationProvider) {
                $where[] = "COALESCE(last_validation_provider, validation_provider, '') = :validation_provider";
                $params['validation_provider'] = $validationProvider;
            }

            $validationStatusRaw = $this->stringFilter($filters, 'validationStatus');
            if (null !== $validationStatusRaw) {
                $where[] = "COALESCE(last_validation_status, validation_status, 'unknown') = :validation_status";
                $params['validation_status'] = AddressRecordPolicy::normalizeValidationStatus($validationStatusRaw);
            }
        }

        $governanceStatusRaw = $this->stringFilter($filters, 'governanceStatus');
        if (null !== $governanceStatusRaw) {
            $where[] = 'governance_status = :governance_status';
            $params['governance_status'] = AddressRecordPolicy::normalizeGovernanceStatus($governanceStatusRaw);
        }

        $revalidationPolicy = AddressRecordPolicy::normalizeRevalidationPolicy($this->stringFilter($filters, 'revalidationPolicy'));
        if (null !== $revalidationPolicy) {
            $where[] = 'revalidation_policy = :revalidation_policy';
            $params['revalidation_policy'] = $revalidationPolicy;
        }

        $hasEvidence = $this->hasEvidenceFilter($filters);
        if (true === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(true);
        } elseif (false === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(false);
        }
    }

    /**
     * @param list<string>         $where
     * @param array<string, mixed> $params
     * @param array<string, mixed> $filters
     */
    protected function applyOperationalPageFilters(array &$where, array &$params, array $filters): ?string
    {
        $this->applyPortfolioFilters($where, $params, $filters);

        $revalidationDueBefore = $this->stringFilter($filters, 'revalidationDueBefore');
        if (null !== $revalidationDueBefore) {
            $where[] = 'revalidation_due_at IS NOT NULL AND revalidation_due_at <= :revalidation_due_before';
            $params['revalidation_due_before'] = $revalidationDueBefore;
        }

        return $revalidationDueBefore;
    }

    /**
     * @param list<string>         $where
     * @param array<string, mixed> $params
     * @param array<string, mixed> $filters
     */
    protected function applyQueueFilter(array &$where, array &$params, array $filters, ?string $revalidationDueBefore): void
    {
        $queue = $this->stringFilter($filters, 'queue');
        if (null === $queue) {
            return;
        }

        $expectedNormalizationVersion = $this->stringFilter($filters, 'expectedNormalizationVersion');
        switch ($queue) {
            case 'dueForRevalidation':
                $params['queue_due_before'] = $revalidationDueBefore ?? $this->currentTimestampAtom();
                $where[] = 'revalidation_due_at IS NOT NULL AND revalidation_due_at <= :queue_due_before';

                return;
            case 'evidenceMissing':
                $where[] = $this->evidencePresenceClause(false);

                return;
            case 'uncertainValidation':
                $where[] = '(validation_status = :queue_validation_status OR last_validation_status = :queue_last_validation_status)';
                $params['queue_validation_status'] = 'uncertain';
                $params['queue_last_validation_status'] = 'uncertain';

                return;
            case 'conflictReview':
                $where[] = 'governance_status = :queue_governance_conflict';
                $params['queue_governance_conflict'] = 'conflict';

                return;
            case 'duplicateReview':
                $where[] = 'governance_status = :queue_governance_duplicate';
                $params['queue_governance_duplicate'] = 'duplicate';

                return;
            case 'staleNormalizationVersion':
                if (null !== $expectedNormalizationVersion) {
                    $where[] = '(normalization_version IS NULL OR normalization_version <> :expected_normalization_version)';
                    $params['expected_normalization_version'] = $expectedNormalizationVersion;
                }

                return;
        }
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $filters
     */
    protected function summaryDueBefore(array &$params, array $filters, string $parameter = 'summary_due_before'): ?string
    {
        $revalidationDueBefore = $this->stringFilter($filters, 'revalidationDueBefore');
        $params[$parameter] = $revalidationDueBefore ?? $this->currentTimestampAtom();

        return $revalidationDueBefore;
    }

    protected function evidencePresenceClause(bool $hasEvidence): string
    {
        $clause = '(raw_input_snapshot IS NOT NULL OR normalized_snapshot IS NOT NULL OR provider_digest IS NOT NULL OR validation_raw IS NOT NULL OR validation_verdict IS NOT NULL)';

        return $hasEvidence ? $clause : 'NOT '.$clause;
    }

    /** @param array<string, mixed> $filters */
    protected function hasEvidenceFilter(array $filters): ?bool
    {
        $value = $filters['hasEvidence'] ?? null;

        return is_bool($value) ? $value : null;
    }

    /** @param array<string, mixed> $filters */
    protected function stringFilter(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /** @param array<string, mixed> $patch
     * @return array<string, mixed>
     */
    protected function normalizeOperationalPatch(string $id, string $currentGovernanceStatus, array $patch): array
    {
        $normalized = [];

        if (array_key_exists('governanceStatus', $patch)) {
            $normalized = array_merge(
                $normalized,
                AddressGovernancePolicy::normalizePatch($currentGovernanceStatus, $id, $patch)
            );
        }

        if (array_key_exists('revalidationDueAt', $patch)) {
            $normalized['revalidation_due_at'] = $this->nullableString($patch['revalidationDueAt'] ?? null);
        }
        if (array_key_exists('revalidationPolicy', $patch)) {
            $normalized['revalidation_policy'] = AddressRecordPolicy::normalizeRevalidationPolicy($this->nullableString($patch['revalidationPolicy'] ?? null));
        }
        if (array_key_exists('lastValidationProvider', $patch)) {
            $normalized['last_validation_provider'] = $this->nullableString($patch['lastValidationProvider'] ?? null);
        }
        if (array_key_exists('lastValidationStatus', $patch)) {
            $normalized['last_validation_status'] = AddressRecordPolicy::normalizeLastValidationStatus($this->nullableString($patch['lastValidationStatus'] ?? null));
        }
        if (array_key_exists('lastValidationScore', $patch)) {
            $normalized['last_validation_score'] = $this->nullableInt($patch['lastValidationScore'] ?? null);
        }

        return $normalized;
    }

    /** @param array<string, mixed> $normalized */
    protected function assertGovernanceTargetsExist(array $normalized, ?string $ownerId, ?string $vendorId): void
    {
        $targets = [
            $normalized['duplicate_of_id'] ?? null,
            $normalized['superseded_by_id'] ?? null,
            $normalized['alias_of_id'] ?? null,
            $normalized['conflict_with_id'] ?? null,
        ];

        foreach ($targets as $target) {
            if (!is_string($target) || '' === trim($target)) {
                continue;
            }

            if (!$this->findDoctrineAddress($target, $ownerId, $vendorId) instanceof AddressEntity) {
                throw new \RuntimeException(sprintf('Governance link target "%s" was not found in the current tenant scope.', $target));
            }
        }
    }

    /** @param array<string, mixed> $normalized */
    protected function applyOperationalPatchToEntity(AddressEntity $entity, array $normalized, \DateTimeImmutable $updatedAt): void
    {
        if (array_key_exists('governance_status', $normalized) && is_string($normalized['governance_status'])) {
            $entity->setGovernanceStatus($normalized['governance_status']);
        }
        if (array_key_exists('duplicate_of_id', $normalized)) {
            $entity->setDuplicateOfId($this->nullableString($normalized['duplicate_of_id']));
        }
        if (array_key_exists('superseded_by_id', $normalized)) {
            $entity->setSupersededById($this->nullableString($normalized['superseded_by_id']));
        }
        if (array_key_exists('alias_of_id', $normalized)) {
            $entity->setAliasOfId($this->nullableString($normalized['alias_of_id']));
        }
        if (array_key_exists('conflict_with_id', $normalized)) {
            $entity->setConflictWithId($this->nullableString($normalized['conflict_with_id']));
        }
        if (array_key_exists('revalidation_due_at', $normalized)) {
            $entity->setRevalidationDueAt($this->nullableDateTime($normalized['revalidation_due_at'] ?? null));
        }
        if (array_key_exists('revalidation_policy', $normalized)) {
            $entity->setRevalidationPolicy($this->nullableString($normalized['revalidation_policy']));
        }
        if (array_key_exists('last_validation_provider', $normalized)) {
            $entity->setLastValidationProvider($this->nullableString($normalized['last_validation_provider']));
        }
        if (array_key_exists('last_validation_status', $normalized)) {
            $entity->setLastValidationStatus($this->nullableString($normalized['last_validation_status']));
        }
        if (array_key_exists('last_validation_score', $normalized)) {
            $entity->setLastValidationScore($this->nullableInt($normalized['last_validation_score']));
        }

        $entity->setUpdatedAt($updatedAt);
    }

    protected function ensureTenantScope(?string $ownerId, ?string $vendorId): void
    {
        if (null === $ownerId && null === $vendorId) {
            throw new \RuntimeException('tenant_scope_required');
        }

        if (null !== $ownerId && null !== $vendorId) {
            throw new \InvalidArgumentException('address_owner_vendor_scope_ambiguous');
        }
    }

    /** @param list<array<string, mixed>> $rows */
    protected function pageCursorFromRows(array $rows, int $limit): ?string
    {
        if (count($rows) !== $limit || [] === $rows) {
            return null;
        }

        $lastRow = end($rows);

        return is_array($lastRow) && isset($lastRow['id']) && is_string($lastRow['id']) ? $lastRow['id'] : null;
    }

    /** @param array<string, mixed> $row */
    protected function mapRowToRecord(array $row): AddressData
    {
        return new AddressData(
            id: $this->stringValue($row['id'] ?? ''),
            ownerId: $this->nullableString($row['owner_id'] ?? null),
            vendorId: $this->nullableString($row['vendor_id'] ?? null),
            line1: $this->stringValue($row['line1'] ?? ''),
            line2: $this->nullableString($row['line2'] ?? null),
            city: $this->stringValue($row['city'] ?? ''),
            region: $this->nullableString($row['region'] ?? null),
            postalCode: $this->nullableString($row['postal_code'] ?? null),
            countryCode: $this->stringValue($row['country_code'] ?? ''),
            line1Norm: $this->nullableString($row['line1_norm'] ?? null),
            cityNorm: $this->nullableString($row['city_norm'] ?? null),
            regionNorm: $this->nullableString($row['region_norm'] ?? null),
            postalCodeNorm: $this->nullableString($row['postal_code_norm'] ?? null),
            latitude: $this->nullableFloat($row['latitude'] ?? null),
            longitude: $this->nullableFloat($row['longitude'] ?? null),
            geohash: $this->nullableString($row['geohash'] ?? null),
            validationStatus: AddressRecordPolicy::normalizeValidationStatus($this->nullableString($row['validation_status'] ?? null) ?? 'unknown'),
            validationProvider: $this->nullableString($row['validation_provider'] ?? null),
            validatedAt: $this->nullableDateString($row['validated_at'] ?? null),
            dedupeKey: $this->nullableString($row['dedupe_key'] ?? null),
            createdAt: $this->stringValue($row['created_at'] ?? ''),
            updatedAt: $this->nullableDateString($row['updated_at'] ?? null),
            deletedAt: $this->nullableDateString($row['deleted_at'] ?? null),
            validationFingerprint: $this->nullableString($row['validation_fingerprint'] ?? null),
            validationRaw: $this->nullableJsonArray($row['validation_raw'] ?? null),
            validationVerdict: $this->nullableJsonArray($row['validation_verdict'] ?? null),
            validationDeliverable: $this->nullableBool($row['validation_deliverable'] ?? null),
            validationGranularity: $this->nullableString($row['validation_granularity'] ?? null),
            validationQuality: $this->nullableInt($row['validation_quality'] ?? null),
            sourceSystem: $this->nullableString($row['source_system'] ?? null),
            sourceType: AddressRecordPolicy::normalizeSourceType($this->nullableString($row['source_type'] ?? null)),
            sourceReference: $this->nullableString($row['source_reference'] ?? null),
            normalizationVersion: $this->nullableString($row['normalization_version'] ?? null),
            rawInputSnapshot: $this->nullableJsonArray($row['raw_input_snapshot'] ?? null),
            normalizedSnapshot: $this->nullableJsonArray($row['normalized_snapshot'] ?? null),
            providerDigest: $this->nullableString($row['provider_digest'] ?? null),
            governanceStatus: AddressRecordPolicy::normalizeGovernanceStatus($this->nullableString($row['governance_status'] ?? null) ?? 'canonical'),
            duplicateOfId: $this->nullableString($row['duplicate_of_id'] ?? null),
            supersededById: $this->nullableString($row['superseded_by_id'] ?? null),
            aliasOfId: $this->nullableString($row['alias_of_id'] ?? null),
            conflictWithId: $this->nullableString($row['conflict_with_id'] ?? null),
            revalidationDueAt: $this->nullableDateString($row['revalidation_due_at'] ?? null),
            revalidationPolicy: AddressRecordPolicy::normalizeRevalidationPolicy($this->nullableString($row['revalidation_policy'] ?? null)),
            lastValidationProvider: $this->nullableString($row['last_validation_provider'] ?? null),
            lastValidationStatus: AddressRecordPolicy::normalizeLastValidationStatus($this->nullableString($row['last_validation_status'] ?? null)),
            lastValidationScore: $this->nullableInt($row['last_validation_score'] ?? null),
        );
    }

    /** @return array<string, mixed>|null */
    protected function nullableJsonArray(mixed $value): ?array
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    protected function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        } elseif (is_int($value) || is_float($value) || is_bool($value)) {
            $value = trim((string) $value);
        } else {
            return null;
        }

        return '' === $value ? null : $value;
    }

    protected function nullableDateString(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return $this->nullableString($value);
    }

    protected function nullableDateTime(mixed $value): ?\DateTimeImmutable
    {
        $value = $this->nullableString($value);

        return null === $value ? null : new \DateTimeImmutable($value);
    }

    protected function nullableInt(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    protected function nullableFloat(mixed $value): ?float
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    protected function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return '';
    }

    protected function nullableBool(mixed $value): ?bool
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return 1 === $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 't', 'yes'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'f', 'no'], true)) {
                return false;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $row */
    protected function intRowValue(array $row, string $key): int
    {
        $value = $row[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @template T of array<string, mixed>
     *
     * @param list<AddressEntity>             $entities
     * @param callable(AddressEntity): string $groupKey
     * @param callable(AddressEntity): T      $groupMeta
     *
     * @return list<T&array{total:int,canonical:int,duplicate:int,superseded:int,alias:int,conflict:int,evidenceBacked:int,evidenceMissing:int,dueForRevalidation:int,uncertainValidation:int,staleNormalization:int}>
     */
    protected function buildGroupedPortfolio(array $entities, callable $groupKey, callable $groupMeta): array
    {
        /** @var array<string, T&array{total:int,canonical:int,duplicate:int,superseded:int,alias:int,conflict:int,evidenceBacked:int,evidenceMissing:int,dueForRevalidation:int,uncertainValidation:int,staleNormalization:int}> $groups */
        $groups = [];

        foreach ($entities as $entity) {
            $key = $groupKey($entity);
            if (!isset($groups[$key])) {
                $groups[$key] = $groupMeta($entity) + [
                    'total' => 0,
                    'canonical' => 0,
                    'duplicate' => 0,
                    'superseded' => 0,
                    'alias' => 0,
                    'conflict' => 0,
                    'evidenceBacked' => 0,
                    'evidenceMissing' => 0,
                    'dueForRevalidation' => 0,
                    'uncertainValidation' => 0,
                    'staleNormalization' => 0,
                ];
            }

            ++$groups[$key]['total'];
            match ($entity->getGovernanceStatus()) {
                'canonical' => ++$groups[$key]['canonical'],
                'duplicate' => ++$groups[$key]['duplicate'],
                'superseded' => ++$groups[$key]['superseded'],
                'alias' => ++$groups[$key]['alias'],
                'conflict' => ++$groups[$key]['conflict'],
                default => null,
            };

            if ($this->hasEvidenceEntity($entity)) {
                ++$groups[$key]['evidenceBacked'];
            } else {
                ++$groups[$key]['evidenceMissing'];
            }

            if (null !== $entity->getRevalidationDueAt() && $entity->getRevalidationDueAt() <= new \DateTimeImmutable('now')) {
                ++$groups[$key]['dueForRevalidation'];
            }

            if ('uncertain' === $entity->getValidationStatus() || 'uncertain' === $entity->getLastValidationStatus()) {
                ++$groups[$key]['uncertainValidation'];
            }

            $meta = $groupMeta($entity);
            if (isset($meta['staleNormalization']) && is_int($meta['staleNormalization'])) {
                $groups[$key]['staleNormalization'] += $meta['staleNormalization'];
            }
        }

        /** @var list<T&array{total:int,canonical:int,duplicate:int,superseded:int,alias:int,conflict:int,evidenceBacked:int,evidenceMissing:int,dueForRevalidation:int,uncertainValidation:int,staleNormalization:int}> $rows */
        $rows = array_values($groups);

        usort($rows, function (array $left, array $right): int {
            if ($left['total'] !== $right['total']) {
                return $right['total'] <=> $left['total'];
            }

            $leftKey = $this->portfolioSortKey($left);
            $rightKey = $this->portfolioSortKey($right);

            return $leftKey <=> $rightKey;
        });

        return $rows;
    }

    protected function asRecord(AddressInterface $address): AddressData
    {
        if ($address instanceof AddressData) {
            return $address;
        }

        return new AddressData(
            id: $address->id(),
            ownerId: $address->ownerId(),
            vendorId: $address->vendorId(),
            line1: $address->line1(),
            line2: $address->line2(),
            city: $address->city(),
            region: $address->region(),
            postalCode: $address->postalCode(),
            countryCode: $address->countryCode(),
            line1Norm: $address->line1Norm(),
            cityNorm: $address->cityNorm(),
            regionNorm: $address->regionNorm(),
            postalCodeNorm: $address->postalCodeNorm(),
            latitude: $address->latitude(),
            longitude: $address->longitude(),
            geohash: $address->geohash(),
            validationStatus: $address->validationStatus(),
            validationProvider: $address->validationProvider(),
            validatedAt: $address->validatedAt(),
            dedupeKey: $address->dedupeKey(),
            createdAt: $address->createdAt(),
            updatedAt: $address->updatedAt(),
            deletedAt: $address->deletedAt(),
            validationFingerprint: $address->validationFingerprint(),
            validationRaw: $address->validationRaw(),
            validationVerdict: $address->validationVerdict(),
            validationDeliverable: $address->validationDeliverable(),
            validationGranularity: $address->validationGranularity(),
            validationQuality: $address->validationQuality(),
            sourceSystem: $address->sourceSystem(),
            sourceType: $address->sourceType(),
            sourceReference: $address->sourceReference(),
            normalizationVersion: $address->normalizationVersion(),
            rawInputSnapshot: $address->rawInputSnapshot(),
            normalizedSnapshot: $address->normalizedSnapshot(),
            providerDigest: $address->providerDigest(),
            governanceStatus: $address->governanceStatus(),
            duplicateOfId: $address->duplicateOfId(),
            supersededById: $address->supersededById(),
            aliasOfId: $address->aliasOfId(),
            conflictWithId: $address->conflictWithId(),
            revalidationDueAt: $address->revalidationDueAt(),
            revalidationPolicy: $address->revalidationPolicy(),
            lastValidationProvider: $address->lastValidationProvider(),
            lastValidationStatus: $address->lastValidationStatus(),
            lastValidationScore: $address->lastValidationScore(),
        );
    }

    protected function appendEvidenceSnapshotInternal(AddressInterface $address, AddressEntity $entity): ?AddressEvidenceSnapshotInterface
    {
        if (!$this->hasEvidence($address)) {
            return null;
        }

        $snapshot = $this->buildEvidenceSnapshot($address);
        $doctrineSnapshot = $this->mapper->toDoctrineSnapshot($snapshot, $entity);
        $this->entityManager->persist($doctrineSnapshot);

        return $snapshot;
    }

    protected function buildEvidenceSnapshot(AddressInterface $address): AddressEvidenceSnapshotInterface
    {
        $validatedBy = $address->validationProvider() ?? $address->lastValidationProvider() ?? $address->sourceSystem();
        $validationScore = $address->lastValidationScore() ?? $address->validationQuality();
        $validationIssues = $address->validationVerdict();
        if (null === $validationIssues && null !== $address->validationRaw() && isset($address->validationRaw()['issues']) && is_array($address->validationRaw()['issues'])) {
            $validationIssues = $address->validationRaw()['issues'];
        }

        return new AddressEvidenceSnapshotData(
            bin2hex(random_bytes(16)),
            $address->id(),
            $address->ownerId(),
            $address->vendorId(),
            $address->sourceSystem(),
            AddressRecordPolicy::normalizeSourceType($address->sourceType()),
            $address->sourceReference(),
            $validatedBy,
            $address->validatedAt(),
            $address->normalizationVersion(),
            $address->rawInputSnapshot(),
            $address->normalizedSnapshot(),
            AddressRecordPolicy::normalizeValidationStatus($address->validationStatus()),
            $validationScore,
            $validationIssues,
            $address->providerDigest(),
            $this->currentTimestampAtom(),
        );
    }

    protected function hasEvidence(AddressInterface $address): bool
    {
        return null !== $address->rawInputSnapshot()
            || null !== $address->normalizedSnapshot()
            || null !== $address->providerDigest()
            || null !== $address->validationRaw()
            || null !== $address->validationVerdict();
    }

    protected function mapSnapshotEntity(AddressEvidenceSnapshotEntity $entity): AddressEvidenceSnapshotInterface
    {
        return new AddressEvidenceSnapshotData(
            $entity->getId(),
            $entity->getAddress()->getId(),
            $entity->getOwnerId(),
            $entity->getVendorId(),
            $entity->getSourceSystem(),
            $entity->getSourceType(),
            $entity->getSourceReference(),
            $entity->getValidatedBy(),
            $entity->getValidatedAt()?->format(DATE_ATOM),
            $entity->getNormalizationVersion(),
            $entity->getRawInputSnapshot(),
            $entity->getNormalizedSnapshot(),
            $entity->getValidationStatus(),
            $entity->getValidationScore(),
            $entity->getValidationIssues(),
            $entity->getProviderDigest(),
            $entity->getCreatedAt()->format(DATE_ATOM),
        );
    }

    protected function copyAddressEntityState(AddressEntity $target, AddressEntity $source): void
    {
        foreach (get_class_methods($target) as $method) {
            if (!str_starts_with($method, 'set')) {
                continue;
            }

            $suffix = substr($method, 3);
            $getter = 'get'.$suffix;
            if (!method_exists($source, $getter)) {
                continue;
            }

            $target->{$method}($source->{$getter}());
        }
    }

    /** @param array<string, mixed> $payload */
    protected function appendOutbox(string $name, array $payload): void
    {
        $json = json_encode(
            AddressOutboxEventMessage::decoratePayload($name, $payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $entity = (new AddressOutboxEntity())
            ->setEventName($name)
            ->setEventVersion(AddressOutboxEventMessage::eventVersion($name))
            ->setPayload($json)
            ->setCreatedAt(new \DateTimeImmutable('now'));

        $this->entityManager->persist($entity);
    }

    protected function governanceLinkId(AddressInterface $address): ?string
    {
        return match (AddressRecordPolicy::normalizeGovernanceStatus($address->governanceStatus())) {
            'duplicate' => $address->duplicateOfId(),
            'superseded' => $address->supersededById(),
            'alias' => $address->aliasOfId(),
            'conflict' => $address->conflictWithId(),
            default => null,
        };
    }

    protected function currentTimestampAtom(): string
    {
        return (new \DateTimeImmutable('now'))->format(DATE_ATOM);
    }

    /** @return array{0:string,1:string} */
    /** @return array{string, string} */
    protected function decodeEvidenceCursor(string $cursor): array
    {
        $decoded = base64_decode($cursor, true);
        if (false === $decoded || !str_contains($decoded, "\n")) {
            throw new \RuntimeException('invalid_evidence_cursor');
        }

        [$createdAt, $id] = explode("\n", $decoded, 2);

        return [$createdAt, $id];
    }

    protected function encodeEvidenceCursor(string $createdAt, string $id): string
    {
        return base64_encode($createdAt."\n".$id);
    }

    /**
     * @return list<AddressEntity>
     */
    /**
     * @param array<string, mixed> $filters
     *
     * @return list<AddressEntity>
     */
    protected function fetchFilteredAddresses(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        array $filters,
        bool $includeSourceSystem = false,
        bool $includeValidation = false,
        bool $includeNormalization = false,
    ): array {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(AddressEntity::class, 'a')
            ->where('a.deletedAt IS NULL');

        $this->applyAddressScope($qb, 'a', $ownerId, $vendorId, $countryCode, $q);
        $this->applyAddressFilters($qb, 'a', $filters, $includeSourceSystem, $includeValidation, $includeNormalization);

        /** @var list<AddressEntity> $entities */
        $entities = $qb->getQuery()->getResult();

        return $entities;
    }

    protected function applyAddressScope(\Doctrine\ORM\QueryBuilder $qb, string $alias, ?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $query): void
    {
        if (null !== $ownerId && null !== $vendorId) {
            throw new \InvalidArgumentException('address_owner_vendor_scope_ambiguous');
        }

        if (null !== $ownerId) {
            $qb->andWhere(sprintf('%s.ownerId = :ownerId', $alias))->setParameter('ownerId', $ownerId);
        } elseif (null !== $vendorId) {
            $qb->andWhere(sprintf('%s.vendorId = :vendorId', $alias))->setParameter('vendorId', $vendorId);
        }

        if (null !== $countryCode && '' !== trim($countryCode)) {
            $qb->andWhere(sprintf('%s.countryCode = :countryCode', $alias))->setParameter('countryCode', $countryCode);
        }

        $query = null !== $query ? trim($query) : null;
        if (null !== $query && '' !== $query) {
            $qb->andWhere(sprintf(
                "LOWER(CONCAT(CONCAT(CONCAT(%1\$s.line1, ' '), %1\$s.city), CONCAT(' ', COALESCE(%1\$s.postalCode, '')))) LIKE :q",
                $alias,
            ))->setParameter('q', '%'.mb_strtolower($query).'%');
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    protected function applyAddressFilters(\Doctrine\ORM\QueryBuilder $qb, string $alias, array $filters, bool $includeSourceSystem = false, bool $includeValidation = false, bool $includeNormalization = false): void
    {
        $sourceType = AddressRecordPolicy::normalizeSourceType($this->stringFilter($filters, 'sourceType'));
        if (null !== $sourceType) {
            $qb->andWhere(sprintf('%s.sourceType = :sourceType', $alias))->setParameter('sourceType', $sourceType);
        }

        if ($includeSourceSystem) {
            $sourceSystem = $this->stringFilter($filters, 'sourceSystem');
            if (null !== $sourceSystem) {
                $qb->andWhere(sprintf('%s.sourceSystem = :sourceSystem', $alias))->setParameter('sourceSystem', $sourceSystem);
            }
        }

        if ($includeValidation) {
            $validationProvider = $this->stringFilter($filters, 'validationProvider');
            if (null !== $validationProvider) {
                $qb->andWhere(sprintf('COALESCE(%s.lastValidationProvider, %s.validationProvider, \'\') = :validationProvider', $alias, $alias))
                    ->setParameter('validationProvider', $validationProvider);
            }

            $validationStatusRaw = $this->stringFilter($filters, 'validationStatus');
            if (null !== $validationStatusRaw) {
                $qb->andWhere(sprintf('COALESCE(%s.lastValidationStatus, %s.validationStatus, \'unknown\') = :validationStatus', $alias, $alias))
                    ->setParameter('validationStatus', AddressRecordPolicy::normalizeValidationStatus($validationStatusRaw));
            }
        }

        $governanceStatusRaw = $this->stringFilter($filters, 'governanceStatus');
        if (null !== $governanceStatusRaw) {
            $qb->andWhere(sprintf('%s.governanceStatus = :governanceStatus', $alias))->setParameter('governanceStatus', AddressRecordPolicy::normalizeGovernanceStatus($governanceStatusRaw));
        }

        $revalidationPolicy = AddressRecordPolicy::normalizeRevalidationPolicy($this->stringFilter($filters, 'revalidationPolicy'));
        if (null !== $revalidationPolicy) {
            $qb->andWhere(sprintf('%s.revalidationPolicy = :revalidationPolicy', $alias))->setParameter('revalidationPolicy', $revalidationPolicy);
        }

        $hasEvidence = $this->hasEvidenceFilter($filters);
        if (true === $hasEvidence) {
            $qb->andWhere($this->evidencePresenceClauseDql($alias, true));
        } elseif (false === $hasEvidence) {
            $qb->andWhere($this->evidencePresenceClauseDql($alias, false));
        }

        if ($includeNormalization) {
            $expectedNormalizationVersion = $this->stringFilter($filters, 'expectedNormalizationVersion');
            if (null !== $expectedNormalizationVersion) {
                $qb->andWhere(sprintf('%s.normalizationVersion IS NULL OR %s.normalizationVersion <> :expectedNormalizationVersion', $alias, $alias))
                    ->setParameter('expectedNormalizationVersion', $expectedNormalizationVersion);
            }
        }
    }

    protected function evidencePresenceClauseDql(string $alias, bool $hasEvidence): string
    {
        $clause = sprintf(
            '(%1$s.rawInputSnapshot IS NOT NULL OR %1$s.normalizedSnapshot IS NOT NULL OR %1$s.providerDigest IS NOT NULL OR %1$s.validationRaw IS NOT NULL OR %1$s.validationVerdict IS NOT NULL)',
            $alias,
        );

        return $hasEvidence ? $clause : 'NOT '.$clause;
    }

    /**
     * @param list<AddressEntity> $entities
     */
    protected function pageCursorFromEntities(array $entities, int $limit): ?string
    {
        if (count($entities) !== $limit || [] === $entities) {
            return null;
        }

        $last = end($entities);

        if (!$last instanceof AddressEntity) {
            return null;
        }

        return $last->getId();
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function portfolioSortKey(array $row): string
    {
        $primary = $row['countryCode'] ?? $row['sourceSystem'] ?? $row['validationProvider'] ?? $row['normalizationVersion'] ?? '';
        $secondary = $row['sourceType'] ?? $row['validationStatus'] ?? '';

        $primaryString = is_scalar($primary) ? (string) $primary : '';
        $secondaryString = is_scalar($secondary) ? (string) $secondary : '';

        return $primaryString.'|'.$secondaryString;
    }

    protected function hasEvidenceEntity(AddressEntity $entity): bool
    {
        return null !== $entity->getRawInputSnapshot()
            || null !== $entity->getNormalizedSnapshot()
            || null !== $entity->getProviderDigest()
            || null !== $entity->getValidationRaw()
            || null !== $entity->getValidationVerdict();
    }

    /**
     * @return list<AddressEntity>
     */
    protected function fetchScopedAddresses(?string $ownerId, ?string $vendorId): array
    {
        return $this->fetchFilteredAddresses($ownerId, $vendorId, null, null, [], false, false, false);
    }
}
