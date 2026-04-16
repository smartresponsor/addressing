<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Repository\Persistence;

use App\Contract\Message\AddressOutboxEventContract;
use App\Contract\Message\AddressRecordPolicy;
use App\Entity\Record\AddressData;
use App\Entity\Record\AddressEvidenceSnapshotData;
use App\EntityInterface\Record\AddressEvidenceSnapshotInterface;
use App\EntityInterface\Record\AddressInterface;
use App\RepositoryInterface\Persistence\AddressPageCriteria;
use App\RepositoryInterface\Persistence\AddressRepositoryInterface;
use App\Service\Application\AddressGovernancePolicy;

final readonly class AddressRepository implements AddressRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    /** @throws \Throwable */
    #[\Override]
    public function create(AddressInterface $address): void
    {
        $this->pdo->beginTransaction();
        try {
            $sql = <<<'SQL'
INSERT INTO address_entity
    (id, owner_id, vendor_id, line1, line2, city, region, postal_code, country_code,
     line1_norm, city_norm, region_norm, postal_code_norm,
     latitude, longitude, geohash,
     validation_status, validation_provider, validated_at,
     dedupe_key, validation_fingerprint, validation_raw, validation_verdict, validation_deliverable, validation_granularity, validation_quality,
     source_system, source_type, source_reference, normalization_version, raw_input_snapshot, normalized_snapshot, provider_digest,
     governance_status, duplicate_of_id, superseded_by_id, alias_of_id, conflict_with_id,
     revalidation_due_at, revalidation_policy, last_validation_provider, last_validation_status, last_validation_score,
     created_at, updated_at, deleted_at)
VALUES
    (:id, :owner_id, :vendor_id, :line1, :line2, :city, :region, :postal_code, :country_code,
     :line1_norm, :city_norm, :region_norm, :postal_code_norm,
     :latitude, :longitude, :geohash,
     :validation_status, :validation_provider, :validated_at,
     :dedupe_key, :validation_fingerprint, :validation_raw, :validation_verdict, :validation_deliverable, :validation_granularity, :validation_quality,
     :source_system, :source_type, :source_reference, :normalization_version, :raw_input_snapshot, :normalized_snapshot, :provider_digest,
     :governance_status, :duplicate_of_id, :superseded_by_id, :alias_of_id, :conflict_with_id,
     :revalidation_due_at, :revalidation_policy, :last_validation_provider, :last_validation_status, :last_validation_score,
     :created_at, :updated_at, :deleted_at)
SQL;

            $stmt = $this->prepare($sql);
            $this->bindForCreate($stmt, $address);
            $stmt->execute();

            $evidenceSnapshot = $this->appendEvidenceSnapshot($address);

            $this->appendOutbox('AddressCreated', [
                'id' => $address->id(),
                'ownerId' => $address->ownerId(),
                'vendorId' => $address->vendorId(),
                'countryCode' => $address->countryCode(),
                'createdAt' => $address->createdAt(),
                'sourceType' => $address->sourceType(),
                'validationStatus' => $address->validationStatus(),
                'hasEvidence' => null !== $address->rawInputSnapshot() || null !== $address->normalizedSnapshot() || null !== $address->providerDigest(),
                'governanceStatus' => $address->governanceStatus(),
                'governanceLinkId' => $this->governanceLinkId($address),
                'revalidationDueAt' => $address->revalidationDueAt(),
                'revalidationPolicy' => $address->revalidationPolicy(),
                'lastValidationStatus' => $address->lastValidationStatus(),
                'evidenceSnapshotId' => $evidenceSnapshot?->id(),
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @throws \Throwable */
    #[\Override]
    public function update(AddressInterface $address): void
    {
        $this->ensureTenantScope($address->ownerId(), $address->vendorId());
        $tenantWhere = $this->tenantWhereClause($address->ownerId(), $address->vendorId());
        $this->pdo->beginTransaction();
        try {
            $sql = <<<'SQL'
UPDATE address_entity SET
    owner_id=:owner_id, vendor_id=:vendor_id, line1=:line1, line2=:line2, city=:city, region=:region,
    postal_code=:postal_code, country_code=:country_code,
    line1_norm=:line1_norm, city_norm=:city_norm, region_norm=:region_norm, postal_code_norm=:postal_code_norm,
    latitude=:latitude, longitude=:longitude, geohash=:geohash,
    validation_status=:validation_status, validation_provider=:validation_provider, validated_at=:validated_at,
    dedupe_key=:dedupe_key, validation_fingerprint=:validation_fingerprint, validation_raw=:validation_raw, validation_verdict=:validation_verdict,
    validation_deliverable=:validation_deliverable, validation_granularity=:validation_granularity, validation_quality=:validation_quality,
    source_system=:source_system, source_type=:source_type, source_reference=:source_reference, normalization_version=:normalization_version,
    raw_input_snapshot=:raw_input_snapshot, normalized_snapshot=:normalized_snapshot, provider_digest=:provider_digest,
    governance_status=:governance_status, duplicate_of_id=:duplicate_of_id, superseded_by_id=:superseded_by_id, alias_of_id=:alias_of_id, conflict_with_id=:conflict_with_id,
    revalidation_due_at=:revalidation_due_at, revalidation_policy=:revalidation_policy, last_validation_provider=:last_validation_provider, last_validation_status=:last_validation_status, last_validation_score=:last_validation_score,
    updated_at=:updated_at, deleted_at=:deleted_at
WHERE id=:id AND %s
SQL;

            $stmt = $this->prepare(sprintf($sql, $tenantWhere));
            $this->bindForUpdate($stmt, $address);
            $stmt->execute();
            if (0 === $stmt->rowCount()) {
                $this->pdo->rollBack();

                return;
            }

            $evidenceSnapshot = $this->appendEvidenceSnapshot($address);

            $this->appendOutbox('AddressUpdated', [
                'id' => $address->id(),
                'updatedAt' => $address->updatedAt() ?? $this->currentTimestampAtom(),
                'sourceType' => $address->sourceType(),
                'validationStatus' => $address->validationStatus(),
                'hasEvidence' => null !== $address->rawInputSnapshot() || null !== $address->normalizedSnapshot() || null !== $address->providerDigest(),
                'governanceStatus' => $address->governanceStatus(),
                'governanceLinkId' => $this->governanceLinkId($address),
                'revalidationDueAt' => $address->revalidationDueAt(),
                'revalidationPolicy' => $address->revalidationPolicy(),
                'lastValidationStatus' => $address->lastValidationStatus(),
                'evidenceSnapshotId' => $evidenceSnapshot?->id(),
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    #[\Override]
    public function appendEvidenceSnapshot(AddressInterface $address): ?AddressEvidenceSnapshotInterface
    {
        if (!$this->hasEvidence($address)) {
            return null;
        }

        $addressEvidenceSnapshot = $this->buildEvidenceSnapshot($address);
        $pdoStatement = $this->prepare(<<<'SQL'
INSERT INTO address_evidence_snapshot
    (id, address_id, owner_id, vendor_id, source_system, source_type, source_reference, validated_by, validated_at,
     normalization_version, raw_input_snapshot, normalized_snapshot, validation_status, validation_score, validation_issues, provider_digest, created_at)
VALUES
    (:id, :address_id, :owner_id, :vendor_id, :source_system, :source_type, :source_reference, :validated_by, :validated_at,
     :normalization_version, :raw_input_snapshot, :normalized_snapshot, :validation_status, :validation_score, :validation_issues, :provider_digest, :created_at)
SQL
        );
        $this->bindEvidenceSnapshot($pdoStatement, $addressEvidenceSnapshot);
        $pdoStatement->execute();

        return $addressEvidenceSnapshot;
    }

    #[\Override]
    public function getLatestEvidenceSnapshot(string $addressId, ?string $ownerId, ?string $vendorId): ?AddressEvidenceSnapshotInterface
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $params = array_merge([':address_id' => $addressId], $this->tenantParams($ownerId, $vendorId));
        $pdoStatement = $this->prepare(
            'SELECT * FROM address_evidence_snapshot WHERE address_id = :address_id AND '.$this->tenantWhereClause($ownerId, $vendorId)
            .' ORDER BY (validated_at IS NOT NULL) DESC, COALESCE(validated_at, created_at) DESC, created_at DESC, id DESC LIMIT 1'
        );
        $pdoStatement->execute($params);
        $row = $pdoStatement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $this->mapEvidenceSnapshot($row) : null;
    }

    /**
     * @return array{'items': list<AddressEvidenceSnapshotInterface>, 'nextCursor': ?string}
     */
    #[\Override]
    public function findEvidenceHistoryPage(string $addressId, ?string $ownerId, ?string $vendorId, int $limit, ?string $cursor): array
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $limit = max(1, min(200, $limit));
        $params = array_merge([':address_id' => $addressId], $this->tenantParams($ownerId, $vendorId));
        $where = ['address_id = :address_id', $this->tenantWhereClause($ownerId, $vendorId)];

        if (null !== $cursor) {
            [$cursorCreatedAt, $cursorId] = $this->decodeEvidenceCursor($cursor);
            $where[] = '(created_at < :cursor_created_at OR (created_at = :cursor_created_at AND id < :cursor_id))';
            $params[':cursor_created_at'] = $cursorCreatedAt;
            $params[':cursor_id'] = $cursorId;
        }

        $sql = 'SELECT * FROM address_evidence_snapshot WHERE '.implode(' AND ', $where)
            .' ORDER BY (validated_at IS NOT NULL) DESC, COALESCE(validated_at, created_at) DESC, created_at DESC, id DESC LIMIT :limit';
        $pdoStatement = $this->prepare($sql);
        $this->bindStatementValues($pdoStatement, $params);
        $pdoStatement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $pdoStatement->execute();
        $safeRows = $this->fetchAssocRows($pdoStatement);

        $items = array_map(fn (array $row): AddressEvidenceSnapshotInterface => $this->mapEvidenceSnapshot($row), $safeRows);

        $nextCursor = null;
        if (count($safeRows) === $limit && [] !== $safeRows) {
            $last = end($safeRows);
            if (is_array($last)) {
                $nextCursor = $this->encodeEvidenceCursor(
                    $this->stringRowValue($last, 'created_at'),
                    $this->stringRowValue($last, 'id')
                );
            }
        }

        return ['items' => $items, 'nextCursor' => $nextCursor];
    }

    #[\Override]
    public function get(string $id, ?string $ownerId, ?string $vendorId): ?AddressInterface
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $params = array_merge([':id' => $id], $this->tenantParams($ownerId, $vendorId));
        $pdoStatement = $this->prepare(
            'SELECT * FROM address_entity WHERE id=:id AND deleted_at IS NULL AND '
            .$this->tenantWhereClause($ownerId, $vendorId)
        );
        $pdoStatement->execute($params);
        $row = $pdoStatement->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        /* @var array<string, mixed> $row */
        return $this->map($row);
    }

    /** @throws \Throwable */
    #[\Override]
    public function delete(string $id, ?string $ownerId, ?string $vendorId): void
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $params = array_merge([':id' => $id], $this->tenantParams($ownerId, $vendorId));
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->prepare(
                'UPDATE address_entity SET deleted_at='.$this->currentTimestampSql().' WHERE id=:id AND deleted_at IS NULL AND '
                .$this->tenantWhereClause($ownerId, $vendorId)
            );
            $stmt->execute($params);
            if (0 === $stmt->rowCount()) {
                $this->pdo->rollBack();

                return;
            }

            $this->appendOutbox('AddressDeleted', [
                'id' => $id,
                'deletedAt' => $this->currentTimestampAtom(),
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    #[\Override]
    public function findByDedupeKey(string $dedupeKey): ?AddressInterface
    {
        $dedupeKey = trim($dedupeKey);
        if ('' === $dedupeKey) {
            return null;
        }

        $pdoStatement = $this->prepare('SELECT * FROM address_entity WHERE dedupe_key = :dedupe AND deleted_at IS NULL');
        $pdoStatement->execute([':dedupe' => $dedupeKey]);
        $row = $pdoStatement->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        /* @var array<string, mixed> $row */
        return $this->map($row);
    }

    /** @throws \Throwable */
    public function markDeleted(string $id, ?string $ownerId, ?string $vendorId): void
    {
        $this->delete($id, $ownerId, $vendorId);
    }

    /**
     * @param array<string, mixed> $patch
     *
     * @throws \Throwable
     */
    #[\Override]
    public function patchOperational(string $id, ?string $ownerId, ?string $vendorId, array $patch): bool
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $current = $this->get($id, $ownerId, $vendorId);
        if (!$current instanceof AddressInterface) {
            return false;
        }

        $normalized = $this->normalizeOperationalPatch($current->id(), $current->governanceStatus(), $patch);
        if ([] === $normalized) {
            return false;
        }

        $this->assertGovTargetsExist($normalized, $ownerId, $vendorId);

        $tenantWhere = $this->tenantWhereClause($ownerId, $vendorId);
        $params = array_merge([':id' => $id], $this->tenantParams($ownerId, $vendorId));
        $set = [];
        foreach ($normalized as $column => $value) {
            $placeholder = ':'.$column;
            $set[] = $column.' = '.$placeholder;
            $params[$placeholder] = $value;
        }
        $set[] = 'updated_at = :updated_at';
        $params[':updated_at'] = $this->currentTimestampLiteral();

        $this->pdo->beginTransaction();
        try {
            $sql = 'UPDATE address_entity SET '.implode(', ', $set).' WHERE id = :id AND deleted_at IS NULL AND '.$tenantWhere;
            $stmt = $this->prepare($sql);
            $stmt->execute($params);
            if (0 === $stmt->rowCount()) {
                $this->pdo->rollBack();

                return false;
            }

            $governanceStatus = array_key_exists('governance_status', $normalized) && is_string($normalized['governance_status'])
                ? $normalized['governance_status']
                : null;
            $governanceLinkId = null === $governanceStatus
                ? null
                : match ($governanceStatus) {
                    'duplicate' => $normalized['duplicate_of_id'] ?? null,
                    'superseded' => $normalized['superseded_by_id'] ?? null,
                    'alias' => $normalized['alias_of_id'] ?? null,
                    'conflict' => $normalized['conflict_with_id'] ?? null,
                    default => null,
                };

            $this->appendOutbox('AddressOperationalPatched', [
                'id' => $id,
                'ownerId' => $ownerId,
                'vendorId' => $vendorId,
                'patchedFields' => array_keys($normalized),
                'governanceStatus' => $governanceStatus,
                'governanceLinkId' => $governanceLinkId,
                'revalidationDueAt' => $normalized['revalidation_due_at'] ?? null,
                'revalidationPolicy' => $normalized['revalidation_policy'] ?? null,
                'lastValidationStatus' => $normalized['last_validation_status'] ?? null,
                'updatedAt' => $params[':updated_at'],
            ]);
            $this->pdo->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * @return array{'items': AddressInterface[], 'nextCursor': ?string}
     */
    #[\Override]
    public function findPage(AddressPageCriteria $criteria): array
    {
        $limit = max(1, min(200, $criteria->limit()));
        $ownerId = $criteria->ownerId();
        $vendorId = $criteria->vendorId();
        $countryCode = $criteria->countryCode();
        $query = $criteria->query();
        $cursor = $criteria->cursor();
        $filters = $criteria->filters();
        $params = $this->tenantParams($ownerId, $vendorId);
        $where = $this->buildScopedSearchWhere($ownerId, $vendorId, $countryCode, $query, $params);
        if (null !== $cursor && '' !== $cursor) {
            $where[] = 'id > :cursor';
            $params[':cursor'] = $cursor;
        }

        $revalidationDueBefore = $this->applyOperationalPageFilters($where, $params, $filters);
        $this->applyQueueFilter($where, $params, $filters, $revalidationDueBefore);

        $sql = 'SELECT * FROM address_entity WHERE '.implode(' AND ', $where).' ORDER BY id ASC LIMIT :limit';
        $pdoStatement = $this->prepare($sql);
        $this->bindStatementValues($pdoStatement, $params);
        $pdoStatement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $pdoStatement->execute();

        $safeRows = $this->fetchAssocRows($pdoStatement);
        $items = array_map(fn (array $row): AddressInterface => $this->map($row), $safeRows);

        return [
            'items' => $items,
            'nextCursor' => $this->pageCursorFromRows($safeRows, $limit),
        ];
    }

    /**
     * @return array{
     *   'addressId':string,
     *   'governanceStatus':?string,
     *   'primaryLinkId':?string,
     *   'linkedToAnother':bool,
     *   'duplicateChildren':int,
     *   'supersededChildren':int,
     *   'aliasChildren':int,
     *   'conflictPeers':int,
     *   'inboundLinkedTotal':int,
     *   'clusterSize':int,
     *   'relatedAddressIds':list<string>
     * }
     */
    #[\Override]
    public function summarizeGovernanceCluster(string $addressId, ?string $ownerId, ?string $vendorId): array
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $current = $this->get($addressId, $ownerId, $vendorId);
        if (!$current instanceof AddressInterface) {
            return [
                'addressId' => $addressId,
                'governanceStatus' => null,
                'primaryLinkId' => null,
                'linkedToAnother' => false,
                'duplicateChildren' => 0,
                'supersededChildren' => 0,
                'aliasChildren' => 0,
                'conflictPeers' => 0,
                'inboundLinkedTotal' => 0,
                'clusterSize' => 0,
                'relatedAddressIds' => [],
            ];
        }

        $params = $this->tenantParams($ownerId, $vendorId);
        $params[':address_id'] = $addressId;
        $tenantWhere = $this->tenantWhereClause($ownerId, $vendorId);
        $sql = 'SELECT '
            .'SUM(CASE WHEN duplicate_of_id = :address_id THEN 1 ELSE 0 END) AS duplicate_children, '
            .'SUM(CASE WHEN superseded_by_id = :address_id THEN 1 ELSE 0 END) AS superseded_children, '
            .'SUM(CASE WHEN alias_of_id = :address_id THEN 1 ELSE 0 END) AS alias_children, '
            .'SUM(CASE WHEN conflict_with_id = :address_id THEN 1 ELSE 0 END) AS conflict_peers '
            .'FROM address_entity WHERE deleted_at IS NULL AND '.$tenantWhere;
        $pdoStatement = $this->prepare($sql);
        $this->bindStatementValues($pdoStatement, $params);
        $pdoStatement->execute();
        $row = $pdoStatement->fetch(\PDO::FETCH_ASSOC);
        /** @var array<string, mixed> $summaryRow */
        $summaryRow = is_array($row) ? $row : [];

        $relatedIds = [];
        $primaryLinkId = $this->governanceLinkId($current);
        if (null !== $primaryLinkId) {
            $relatedIds[] = $primaryLinkId;
        }

        $listSql = 'SELECT id FROM address_entity WHERE deleted_at IS NULL AND '.$tenantWhere
            .' AND (duplicate_of_id = :address_id OR superseded_by_id = :address_id OR alias_of_id = :address_id OR conflict_with_id = :address_id) ORDER BY id ASC';
        $listStmt = $this->prepare($listSql);
        foreach ($params as $parameterName => $parameterValue) {
            $listStmt->bindValue($parameterName, $parameterValue);
        }
        $listStmt->execute();
        while (($id = $listStmt->fetchColumn()) !== false) {
            if (is_string($id) && '' !== $id) {
                $relatedIds[] = $id;
            }
        }

        $relatedIds = array_values(array_unique($relatedIds));
        $duplicateChildren = $this->intRowValue($summaryRow, 'duplicate_children');
        $supersededChildren = $this->intRowValue($summaryRow, 'superseded_children');
        $aliasChildren = $this->intRowValue($summaryRow, 'alias_children');
        $conflictPeers = $this->intRowValue($summaryRow, 'conflict_peers');
        $inboundLinkedTotal = $duplicateChildren + $supersededChildren + $aliasChildren + $conflictPeers;

        return [
            'addressId' => $addressId,
            'governanceStatus' => $current->governanceStatus(),
            'primaryLinkId' => $primaryLinkId,
            'linkedToAnother' => null !== $primaryLinkId,
            'duplicateChildren' => $duplicateChildren,
            'supersededChildren' => $supersededChildren,
            'aliasChildren' => $aliasChildren,
            'conflictPeers' => $conflictPeers,
            'inboundLinkedTotal' => $inboundLinkedTotal,
            'clusterSize' => 1 + $inboundLinkedTotal + (null !== $primaryLinkId ? 1 : 0),
            'relatedAddressIds' => $relatedIds,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *   'total':int,
     *   'dueForRevalidation':int,
     *   'evidenceMissing':int,
     *   'uncertainValidation':int,
     *   'conflictReview':int,
     *   'duplicateReview':int,
     *   'staleNormalizationVersion':int
     * }
     */
    #[\Override]
    public function summarizeOperationalQueues(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, array $filters = []): array
    {
        $params = $this->tenantParams($ownerId, $vendorId);
        $where = $this->buildScopedSearchWhere($ownerId, $vendorId, $countryCode, $q, $params);
        $this->applyPortfolioFilters($where, $params, $filters);

        $revalidationDueBefore = $this->summaryDueBefore($params, $filters, ':queue_due_before');
        if (null !== $revalidationDueBefore) {
            $where[] = 'revalidation_due_at IS NOT NULL AND revalidation_due_at <= :revalidation_due_before';
            $params[':revalidation_due_before'] = $revalidationDueBefore;
        }

        $expectedNormalizationVersion = $this->stringFilter($filters, 'expectedNormalizationVersion');
        $baseWhere = implode(' AND ', $where);
        $staleSql = null !== $expectedNormalizationVersion
            ? 'SUM(CASE WHEN normalization_version IS NULL OR normalization_version <> :expected_normalization_version THEN 1 ELSE 0 END)'
            : '0';
        if (null !== $expectedNormalizationVersion) {
            $params[':expected_normalization_version'] = $expectedNormalizationVersion;
        }

        $sql = 'SELECT '
            .'COUNT(*) AS total, '
            .'SUM(CASE WHEN revalidation_due_at IS NOT NULL AND revalidation_due_at <= :queue_due_before THEN 1 ELSE 0 END) AS due_for_revalidation, '
            .'SUM(CASE WHEN '.$this->evidencePresenceClause(false).' THEN 1 ELSE 0 END) AS evidence_missing, '
            ."SUM(CASE WHEN validation_status = 'uncertain' OR last_validation_status = 'uncertain' THEN 1 ELSE 0 END) AS uncertain_validation, "
            ."SUM(CASE WHEN governance_status = 'conflict' THEN 1 ELSE 0 END) AS conflict_review, "
            ."SUM(CASE WHEN governance_status = 'duplicate' THEN 1 ELSE 0 END) AS duplicate_review, "
            .$staleSql.' AS stale_normalization_version '
            .'FROM address_entity WHERE '.$baseWhere;

        $pdoStatement = $this->prepare($sql);
        $this->bindStatementValues($pdoStatement, $params);
        $pdoStatement->execute();
        $row = $pdoStatement->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return [
                'total' => 0,
                'dueForRevalidation' => 0,
                'evidenceMissing' => 0,
                'uncertainValidation' => 0,
                'conflictReview' => 0,
                'duplicateReview' => 0,
                'staleNormalizationVersion' => 0,
            ];
        }

        return [
            'total' => $this->intRowValue($row, 'total'),
            'dueForRevalidation' => $this->intRowValue($row, 'due_for_revalidation'),
            'evidenceMissing' => $this->intRowValue($row, 'evidence_missing'),
            'uncertainValidation' => $this->intRowValue($row, 'uncertain_validation'),
            'conflictReview' => $this->intRowValue($row, 'conflict_review'),
            'duplicateReview' => $this->intRowValue($row, 'duplicate_review'),
            'staleNormalizationVersion' => $this->intRowValue($row, 'stale_normalization_version'),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   countryCode:string,
     *   'total':int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   'evidenceMissing':int,
     *   'dueForRevalidation':int,
     *   'uncertainValidation':int
     * }>
     */
    #[\Override]
    public function summarizeCountryPortfolio(?string $ownerId, ?string $vendorId, ?string $q, array $filters = []): array
    {
        $params = $this->tenantParams($ownerId, $vendorId);
        $where = $this->buildScopedSearchWhere($ownerId, $vendorId, null, $q, $params);
        $this->applyPortfolioFilters($where, $params, $filters);
        $this->summaryDueBefore($params, $filters);

        $sql = 'SELECT country_code AS country_code, '
            .'COUNT(*) AS total, '
            ."SUM(CASE WHEN governance_status = 'canonical' THEN 1 ELSE 0 END) AS canonical_count, "
            ."SUM(CASE WHEN governance_status = 'duplicate' THEN 1 ELSE 0 END) AS duplicate_count, "
            ."SUM(CASE WHEN governance_status = 'superseded' THEN 1 ELSE 0 END) AS superseded_count, "
            ."SUM(CASE WHEN governance_status = 'alias' THEN 1 ELSE 0 END) AS alias_count, "
            ."SUM(CASE WHEN governance_status = 'conflict' THEN 1 ELSE 0 END) AS conflict_count, "
            .'SUM(CASE WHEN '.$this->evidencePresenceClause(true).' THEN 1 ELSE 0 END) AS evidence_backed_count, '
            .'SUM(CASE WHEN '.$this->evidencePresenceClause(false).' THEN 1 ELSE 0 END) AS evidence_missing_count, '
            .'SUM(CASE WHEN revalidation_due_at IS NOT NULL AND revalidation_due_at <= :summary_due_before THEN 1 ELSE 0 END) AS due_for_revalidation_count, '
            ."SUM(CASE WHEN validation_status = 'uncertain' OR last_validation_status = 'uncertain' THEN 1 ELSE 0 END) AS uncertain_validation_count "
            .'FROM address_entity WHERE '.implode(' AND ', $where)
            .' GROUP BY country_code ORDER BY total DESC, country_code ASC';

        $pdoStatement = $this->prepare($sql);
        $this->bindStatementValues($pdoStatement, $params);
        $pdoStatement->execute();
        $rows = $this->fetchAssocRows($pdoStatement);

        return array_map(fn (array $row): array => [
            'countryCode' => $this->stringRowValue($row, 'country_code'),
            'total' => $this->intRowValue($row, 'total'),
            'canonical' => $this->intRowValue($row, 'canonical_count'),
            'duplicate' => $this->intRowValue($row, 'duplicate_count'),
            'superseded' => $this->intRowValue($row, 'superseded_count'),
            'alias' => $this->intRowValue($row, 'alias_count'),
            'conflict' => $this->intRowValue($row, 'conflict_count'),
            'evidenceBacked' => $this->intRowValue($row, 'evidence_backed_count'),
            'evidenceMissing' => $this->intRowValue($row, 'evidence_missing_count'),
            'dueForRevalidation' => $this->intRowValue($row, 'due_for_revalidation_count'),
            'uncertainValidation' => $this->intRowValue($row, 'uncertain_validation_count'),
        ], $rows);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   sourceSystem:string,
     *   sourceType:string,
     *   'total':int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   'evidenceMissing':int,
     *   'dueForRevalidation':int,
     *   'uncertainValidation':int
     * }>
     */
    #[\Override]
    public function summarizeSourcePortfolio(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, array $filters = []): array
    {
        $params = $this->tenantParams($ownerId, $vendorId);
        $where = $this->buildScopedSearchWhere($ownerId, $vendorId, $countryCode, $q, $params);
        $this->applyPortfolioFilters($where, $params, $filters, true);
        $this->summaryDueBefore($params, $filters);

        $sql = 'SELECT COALESCE(source_system, "") AS source_system, COALESCE(source_type, "") AS source_type, '
            .'COUNT(*) AS total, '
            ."SUM(CASE WHEN governance_status = 'canonical' THEN 1 ELSE 0 END) AS canonical_count, "
            ."SUM(CASE WHEN governance_status = 'duplicate' THEN 1 ELSE 0 END) AS duplicate_count, "
            ."SUM(CASE WHEN governance_status = 'superseded' THEN 1 ELSE 0 END) AS superseded_count, "
            ."SUM(CASE WHEN governance_status = 'alias' THEN 1 ELSE 0 END) AS alias_count, "
            ."SUM(CASE WHEN governance_status = 'conflict' THEN 1 ELSE 0 END) AS conflict_count, "
            .'SUM(CASE WHEN '.$this->evidencePresenceClause(true).' THEN 1 ELSE 0 END) AS evidence_backed_count, '
            .'SUM(CASE WHEN '.$this->evidencePresenceClause(false).' THEN 1 ELSE 0 END) AS evidence_missing_count, '
            .'SUM(CASE WHEN revalidation_due_at IS NOT NULL AND revalidation_due_at <= :summary_due_before THEN 1 ELSE 0 END) AS due_for_revalidation_count, '
            ."SUM(CASE WHEN validation_status = 'uncertain' OR last_validation_status = 'uncertain' THEN 1 ELSE 0 END) AS uncertain_validation_count "
            .'FROM address_entity WHERE '.implode(' AND ', $where)
            .' GROUP BY COALESCE(source_system, ""), COALESCE(source_type, "")'
            .' ORDER BY total DESC, source_system ASC, source_type ASC';

        $pdoStatement = $this->prepare($sql);
        $this->bindStatementValues($pdoStatement, $params);
        $pdoStatement->execute();
        $rows = $this->fetchAssocRows($pdoStatement);

        return array_map(fn (array $row): array => [
            'sourceSystem' => $this->stringRowValue($row, 'source_system'),
            'sourceType' => $this->stringRowValue($row, 'source_type'),
            'total' => $this->intRowValue($row, 'total'),
            'canonical' => $this->intRowValue($row, 'canonical_count'),
            'duplicate' => $this->intRowValue($row, 'duplicate_count'),
            'superseded' => $this->intRowValue($row, 'superseded_count'),
            'alias' => $this->intRowValue($row, 'alias_count'),
            'conflict' => $this->intRowValue($row, 'conflict_count'),
            'evidenceBacked' => $this->intRowValue($row, 'evidence_backed_count'),
            'evidenceMissing' => $this->intRowValue($row, 'evidence_missing_count'),
            'dueForRevalidation' => $this->intRowValue($row, 'due_for_revalidation_count'),
            'uncertainValidation' => $this->intRowValue($row, 'uncertain_validation_count'),
        ], $rows);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   validationProvider:string,
     *   validationStatus:string,
     *   'total':int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   'evidenceMissing':int,
     *   'dueForRevalidation':int,
     *   'uncertainValidation':int
     * }>
     */
    #[\Override]
    public function summarizeValidationPortfolio(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, array $filters = []): array
    {
        $params = $this->tenantParams($ownerId, $vendorId);
        $where = $this->buildScopedSearchWhere($ownerId, $vendorId, $countryCode, $q, $params);
        $this->applyPortfolioFilters($where, $params, $filters, true, true);
        $this->summaryDueBefore($params, $filters);

        $providerExpr = 'COALESCE(last_validation_provider, validation_provider, "")';
        $statusExpr = 'COALESCE(last_validation_status, validation_status, "unknown")';
        $sql = 'SELECT '.$providerExpr.' AS validation_provider, '.$statusExpr.' AS validation_status, '
            .'COUNT(*) AS total, '
            ."SUM(CASE WHEN governance_status = 'canonical' THEN 1 ELSE 0 END) AS canonical_count, "
            ."SUM(CASE WHEN governance_status = 'duplicate' THEN 1 ELSE 0 END) AS duplicate_count, "
            ."SUM(CASE WHEN governance_status = 'superseded' THEN 1 ELSE 0 END) AS superseded_count, "
            ."SUM(CASE WHEN governance_status = 'alias' THEN 1 ELSE 0 END) AS alias_count, "
            ."SUM(CASE WHEN governance_status = 'conflict' THEN 1 ELSE 0 END) AS conflict_count, "
            .'SUM(CASE WHEN '.$this->evidencePresenceClause(true).' THEN 1 ELSE 0 END) AS evidence_backed_count, '
            .'SUM(CASE WHEN '.$this->evidencePresenceClause(false).' THEN 1 ELSE 0 END) AS evidence_missing_count, '
            .'SUM(CASE WHEN revalidation_due_at IS NOT NULL AND revalidation_due_at <= :summary_due_before THEN 1 ELSE 0 END) AS due_for_revalidation_count, '
            ."SUM(CASE WHEN validation_status = 'uncertain' OR last_validation_status = 'uncertain' THEN 1 ELSE 0 END) AS uncertain_validation_count "
            .'FROM address_entity WHERE '.implode(' AND ', $where)
            .' GROUP BY '.$providerExpr.', '.$statusExpr
            .' ORDER BY total DESC, validation_provider ASC, validation_status ASC';

        $pdoStatement = $this->prepare($sql);
        $this->bindStatementValues($pdoStatement, $params);
        $pdoStatement->execute();
        $rows = $this->fetchAssocRows($pdoStatement);

        return array_map(fn (array $row): array => [
            'validationProvider' => $this->stringRowValue($row, 'validation_provider'),
            'validationStatus' => $this->stringRowValue($row, 'validation_status', 'unknown'),
            'total' => $this->intRowValue($row, 'total'),
            'canonical' => $this->intRowValue($row, 'canonical_count'),
            'duplicate' => $this->intRowValue($row, 'duplicate_count'),
            'superseded' => $this->intRowValue($row, 'superseded_count'),
            'alias' => $this->intRowValue($row, 'alias_count'),
            'conflict' => $this->intRowValue($row, 'conflict_count'),
            'evidenceBacked' => $this->intRowValue($row, 'evidence_backed_count'),
            'evidenceMissing' => $this->intRowValue($row, 'evidence_missing_count'),
            'dueForRevalidation' => $this->intRowValue($row, 'due_for_revalidation_count'),
            'uncertainValidation' => $this->intRowValue($row, 'uncertain_validation_count'),
        ], $rows);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<int, string>
     */
    private function buildScopedSearchWhere(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $query, array &$params): array
    {
        $this->ensureTenantScope($ownerId, $vendorId);

        $where = ['deleted_at IS NULL', $this->tenantWhereClause($ownerId, $vendorId)];
        if (null !== $countryCode && '' !== $countryCode) {
            $where[] = 'country_code = :country_code';
            $params[':country_code'] = $countryCode;
        }

        $this->appendSearchQueryFilter($where, $params, $this->databaseDriverName(), $query);

        return $where;
    }

    /**
     * @param array<int, string>   $where
     * @param array<string, mixed> $params
     * @param array<string, mixed> $filters
     */
    private function applyPortfolioFilters(
        array &$where,
        array &$params,
        array $filters,
        bool $includeSourceSystem = false,
        bool $includeValidation = false,
    ): void {
        $sourceType = AddressRecordPolicy::normalizeSourceType($this->stringFilter($filters, 'sourceType'));
        if (null !== $sourceType) {
            $where[] = 'source_type = :source_type';
            $params[':source_type'] = $sourceType;
        }

        if ($includeSourceSystem) {
            $sourceSystem = $this->stringFilter($filters, 'sourceSystem');
            if (null !== $sourceSystem) {
                $where[] = 'source_system = :source_system';
                $params[':source_system'] = $sourceSystem;
            }
        }

        if ($includeValidation) {
            $validationProvider = $this->stringFilter($filters, 'validationProvider');
            if (null !== $validationProvider) {
                $where[] = 'COALESCE(last_validation_provider, validation_provider, "") = :validation_provider';
                $params[':validation_provider'] = $validationProvider;
            }

            $validationStatusRaw = $this->stringFilter($filters, 'validationStatus');
            $validationStatus = AddressRecordPolicy::normalizeValidationStatus($validationStatusRaw);
            if (null !== $validationStatusRaw) {
                $where[] = 'COALESCE(last_validation_status, validation_status, "unknown") = :validation_status';
                $params[':validation_status'] = $validationStatus;
            }
        }

        $governanceStatusRaw = $this->stringFilter($filters, 'governanceStatus');
        $governanceStatus = AddressRecordPolicy::normalizeGovernanceStatus($governanceStatusRaw);
        if (null !== $governanceStatusRaw) {
            $where[] = 'governance_status = :governance_status';
            $params[':governance_status'] = $governanceStatus;
        }

        $revalidationPolicy = AddressRecordPolicy::normalizeRevalidationPolicy($this->stringFilter($filters, 'revalidationPolicy'));
        if (null !== $revalidationPolicy) {
            $where[] = 'revalidation_policy = :revalidation_policy';
            $params[':revalidation_policy'] = $revalidationPolicy;
        }

        $hasEvidence = $this->hasEvidenceFilter($filters);
        if (true === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(true);
        } elseif (false === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(false);
        }
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $filters
     */
    private function summaryDueBefore(array &$params, array $filters, string $parameter = ':summary_due_before'): ?string
    {
        $revalidationDueBefore = $this->stringFilter($filters, 'revalidationDueBefore');
        $params[$parameter] = $revalidationDueBefore ?? $this->currentTimestampLiteral();

        return $revalidationDueBefore;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAssocRows(\PDOStatement $pdoStatement): array
    {
        $rows = $pdoStatement->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $safeRows = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $safeRows[] = $row;
            }
        }

        return $safeRows;
    }

    private function databaseDriverName(): string
    {
        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return is_string($driverAttr) ? $driverAttr : '';
    }

    private function searchableAddressTextClause(string $driver): string
    {
        $operator = 'pgsql' === $driver ? 'ILIKE' : 'LIKE';

        return 'lower(line1 || \' \' || city || \' \' || coalesce(postal_code,\'\')) '.$operator.' lower(:q)';
    }

    /**
     * @param array<int, string>   $where
     * @param array<string, mixed> $params
     * @param array<string, mixed> $filters
     */
    private function applyOperationalPageFilters(array &$where, array &$params, array $filters): ?string
    {
        $this->applyPortfolioFilters($where, $params, $filters);

        $revalidationDueBefore = $this->stringFilter($filters, 'revalidationDueBefore');
        if (null !== $revalidationDueBefore) {
            $where[] = 'revalidation_due_at IS NOT NULL AND revalidation_due_at <= :revalidation_due_before';
            $params[':revalidation_due_before'] = $revalidationDueBefore;
        }

        return $revalidationDueBefore;
    }

    /**
     * @param array<int, string>   $where
     * @param array<string, mixed> $params
     * @param array<string, mixed> $filters
     */
    private function applyQueueFilter(array &$where, array &$params, array $filters, ?string $revalidationDueBefore): void
    {
        $queue = $this->stringFilter($filters, 'queue');
        if (null === $queue) {
            return;
        }

        $expectedNormalizationVersion = $this->stringFilter($filters, 'expectedNormalizationVersion');
        switch ($queue) {
            case 'dueForRevalidation':
                $queueDueBefore = $revalidationDueBefore ?? $this->currentTimestampLiteral();
                $where[] = 'revalidation_due_at IS NOT NULL AND revalidation_due_at <= :queue_due_before';
                $params[':queue_due_before'] = $queueDueBefore;

                return;
            case 'evidenceMissing':
                $where[] = $this->evidencePresenceClause(false);

                return;
            case 'uncertainValidation':
                $where[] = '(validation_status = :queue_validation_status OR last_validation_status = :queue_last_validation_status)';
                $params[':queue_validation_status'] = 'uncertain';
                $params[':queue_last_validation_status'] = 'uncertain';

                return;
            case 'conflictReview':
                $where[] = 'governance_status = :queue_governance_conflict';
                $params[':queue_governance_conflict'] = 'conflict';

                return;
            case 'duplicateReview':
                $where[] = 'governance_status = :queue_governance_duplicate';
                $params[':queue_governance_duplicate'] = 'duplicate';

                return;
            case 'staleNormalizationVersion':
                if (null !== $expectedNormalizationVersion) {
                    $where[] = '(normalization_version IS NULL OR normalization_version <> :expected_normalization_version)';
                    $params[':expected_normalization_version'] = $expectedNormalizationVersion;
                }

                return;
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function pageCursorFromRows(array $rows, int $limit): ?string
    {
        if (count($rows) !== $limit || [] === $rows) {
            return null;
        }

        $lastRow = end($rows);

        return is_array($lastRow) && array_key_exists('id', $lastRow)
            ? $this->stringRowValue($lastRow, 'id')
            : null;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<int, string>   $where
     */
    private function appendSearchQueryFilter(array &$where, array &$params, string $driver, ?string $query): void
    {
        if (null === $query || '' === trim($query)) {
            return;
        }

        $where[] = $this->searchableAddressTextClause($driver);
        $params[':q'] = '%'.$query.'%';
    }

    /**
     * @param array<string, mixed> $params
     */
    private function bindStatementValues(\PDOStatement $statement, array $params): void
    {
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
    }

    private function bindForCreate(\PDOStatement $pdoStatement, AddressInterface $address): void
    {
        $normalized = $this->normalizedFields($address);
        $this->bindCommonValues($pdoStatement, $address, $normalized);
        $pdoStatement->bindValue(':created_at', $address->createdAt());
        $pdoStatement->bindValue(':updated_at', $address->updatedAt());
        $pdoStatement->bindValue(':deleted_at', $address->deletedAt());
    }

    private function bindForUpdate(\PDOStatement $pdoStatement, AddressInterface $address): void
    {
        $normalized = $this->normalizedFields($address);
        $this->bindCommonValues($pdoStatement, $address, $normalized);
        $pdoStatement->bindValue(':updated_at', $address->updatedAt());
        $pdoStatement->bindValue(':deleted_at', $address->deletedAt());
    }

    /**
     * @param array{line1_norm: ?string, city_norm: ?string, region_norm: ?string, postal_code_norm: ?string} $normalized
     */
    private function bindCommonValues(\PDOStatement $pdoStatement, AddressInterface $address, array $normalized): void
    {
        $pdoStatement->bindValue(':id', $address->id());
        $pdoStatement->bindValue(':owner_id', $address->ownerId());
        $pdoStatement->bindValue(':vendor_id', $address->vendorId());
        $pdoStatement->bindValue(':line1', $address->line1());
        $pdoStatement->bindValue(':line2', $address->line2());
        $pdoStatement->bindValue(':city', $address->city());
        $pdoStatement->bindValue(':region', $address->region());
        $pdoStatement->bindValue(':postal_code', $address->postalCode());
        $pdoStatement->bindValue(':country_code', $address->countryCode());
        $pdoStatement->bindValue(':line1_norm', $normalized['line1_norm']);
        $pdoStatement->bindValue(':city_norm', $normalized['city_norm']);
        $pdoStatement->bindValue(':region_norm', $normalized['region_norm']);
        $pdoStatement->bindValue(':postal_code_norm', $normalized['postal_code_norm']);
        $pdoStatement->bindValue(':latitude', $address->latitude());
        $pdoStatement->bindValue(':longitude', $address->longitude());
        $pdoStatement->bindValue(':geohash', $address->geohash());
        $pdoStatement->bindValue(':validation_status', AddressRecordPolicy::normalizeValidationStatus($address->validationStatus()));
        $pdoStatement->bindValue(':validation_provider', $address->validationProvider());
        $pdoStatement->bindValue(':validated_at', $address->validatedAt());
        $pdoStatement->bindValue(':dedupe_key', $this->effectiveDedupeKey($address, $normalized));
        $pdoStatement->bindValue(':validation_fingerprint', $address->validationFingerprint());
        $pdoStatement->bindValue(':validation_raw', $this->encodeJsonNullable($address->validationRaw()));
        $pdoStatement->bindValue(':validation_verdict', $this->encodeJsonNullable($address->validationVerdict()));
        $deliverable = $address->validationDeliverable();
        $pdoStatement->bindValue(':validation_deliverable', null === $deliverable ? null : (int) $deliverable);
        $pdoStatement->bindValue(':validation_granularity', $address->validationGranularity());
        $pdoStatement->bindValue(':validation_quality', $address->validationQuality());
        $pdoStatement->bindValue(':source_system', $address->sourceSystem());
        $pdoStatement->bindValue(':source_type', AddressRecordPolicy::normalizeSourceType($address->sourceType()));
        $pdoStatement->bindValue(':source_reference', $address->sourceReference());
        $pdoStatement->bindValue(':normalization_version', $address->normalizationVersion());
        $pdoStatement->bindValue(':raw_input_snapshot', $this->encodeJsonNullable($address->rawInputSnapshot()));
        $pdoStatement->bindValue(':normalized_snapshot', $this->encodeJsonNullable($address->normalizedSnapshot()));
        $pdoStatement->bindValue(':provider_digest', $address->providerDigest());
        $pdoStatement->bindValue(':governance_status', AddressRecordPolicy::normalizeGovernanceStatus($address->governanceStatus()));
        $pdoStatement->bindValue(':duplicate_of_id', $this->sanitizeGovernanceLink($address->duplicateOfId(), $address->id()));
        $pdoStatement->bindValue(':superseded_by_id', $this->sanitizeGovernanceLink($address->supersededById(), $address->id()));
        $pdoStatement->bindValue(':alias_of_id', $this->sanitizeGovernanceLink($address->aliasOfId(), $address->id()));
        $pdoStatement->bindValue(':conflict_with_id', $this->sanitizeGovernanceLink($address->conflictWithId(), $address->id()));
        $pdoStatement->bindValue(':revalidation_due_at', $address->revalidationDueAt());
        $pdoStatement->bindValue(':revalidation_policy', AddressRecordPolicy::normalizeRevalidationPolicy($address->revalidationPolicy()));
        $pdoStatement->bindValue(':last_validation_provider', $address->lastValidationProvider());
        $pdoStatement->bindValue(':last_validation_status', AddressRecordPolicy::normalizeLastValidationStatus($address->lastValidationStatus()));
        $pdoStatement->bindValue(':last_validation_score', $address->lastValidationScore());
    }

    /**
     * @param array<string, mixed> $r
     */
    private function map(array $r): AddressData
    {
        $validationRaw = $this->decodeJsonNullable($r['validation_raw'] ?? null);
        $validationVerdict = $this->decodeJsonNullable($r['validation_verdict'] ?? null);
        $validationDeliverable = $this->asNullableBool($r['validation_deliverable'] ?? null);
        $validationGranularity = $this->asNullableString($r['validation_granularity'] ?? null);
        $validationQuality = $this->asNullableInt($r['validation_quality'] ?? null);
        $rawInputSnapshot = $this->decodeJsonNullable($r['raw_input_snapshot'] ?? null);
        $normalizedSnapshot = $this->decodeJsonNullable($r['normalized_snapshot'] ?? null);

        return new AddressData(
            $this->asString($r['id'] ?? null, 'id'),
            $this->asNullableString($r['owner_id'] ?? null),
            $this->asNullableString($r['vendor_id'] ?? null),
            $this->asString($r['line1'] ?? null, 'line1'),
            $this->asNullableString($r['line2'] ?? null),
            $this->asString($r['city'] ?? null, 'city'),
            $this->asNullableString($r['region'] ?? null),
            $this->asNullableString($r['postal_code'] ?? null),
            $this->asString($r['country_code'] ?? null, 'country_code'),
            $this->asNullableString($r['line1_norm'] ?? null),
            $this->asNullableString($r['city_norm'] ?? null),
            $this->asNullableString($r['region_norm'] ?? null),
            $this->asNullableString($r['postal_code_norm'] ?? null),
            $this->asNullableFloat($r['latitude'] ?? null),
            $this->asNullableFloat($r['longitude'] ?? null),
            $this->asNullableString($r['geohash'] ?? null),
            AddressRecordPolicy::normalizeValidationStatus($this->asString($r['validation_status'] ?? null, 'validation_status')),
            $this->asNullableString($r['validation_provider'] ?? null),
            $this->asNullableString($r['validated_at'] ?? null),
            $this->asNullableString($r['dedupe_key'] ?? null),
            $this->asString($r['created_at'] ?? null, 'created_at'),
            $this->asNullableString($r['updated_at'] ?? null),
            $this->asNullableString($r['deleted_at'] ?? null),
            $this->asNullableString($r['validation_fingerprint'] ?? null),
            $validationRaw,
            $validationVerdict,
            $validationDeliverable,
            $validationGranularity,
            $validationQuality,
            $this->asNullableString($r['source_system'] ?? null),
            AddressRecordPolicy::normalizeSourceType($this->asNullableString($r['source_type'] ?? null)),
            $this->asNullableString($r['source_reference'] ?? null),
            $this->asNullableString($r['normalization_version'] ?? null),
            $rawInputSnapshot,
            $normalizedSnapshot,
            $this->asNullableString($r['provider_digest'] ?? null),
            AddressRecordPolicy::normalizeGovernanceStatus($this->asNullableString($r['governance_status'] ?? null) ?? 'canonical'),
            $this->asNullableString($r['duplicate_of_id'] ?? null),
            $this->asNullableString($r['superseded_by_id'] ?? null),
            $this->asNullableString($r['alias_of_id'] ?? null),
            $this->asNullableString($r['conflict_with_id'] ?? null),
            $this->asNullableString($r['revalidation_due_at'] ?? null),
            AddressRecordPolicy::normalizeRevalidationPolicy($this->asNullableString($r['revalidation_policy'] ?? null)),
            $this->asNullableString($r['last_validation_provider'] ?? null),
            AddressRecordPolicy::normalizeLastValidationStatus($this->asNullableString($r['last_validation_status'] ?? null)),
            $this->asNullableInt($r['last_validation_score'] ?? null)
        );
    }

    private function hasEvidence(AddressInterface $address): bool
    {
        if (null !== $address->rawInputSnapshot()) {
            return true;
        }
        if (null !== $address->normalizedSnapshot()) {
            return true;
        }
        if (null !== $address->providerDigest()) {
            return true;
        }
        if (null !== $address->validationRaw()) {
            return true;
        }

        return null !== $address->validationVerdict();
    }

    private function buildEvidenceSnapshot(AddressInterface $address): AddressEvidenceSnapshotInterface
    {
        $createdAt = $this->currentTimestampLiteral();
        $validatedBy = $address->validationProvider() ?? $address->lastValidationProvider() ?? $address->sourceSystem();
        $validationScore = $address->lastValidationScore() ?? $address->validationQuality();
        $validationIssues = $address->validationVerdict();
        if (null === $validationIssues && null !== $address->validationRaw() && isset($address->validationRaw()['issues']) && is_array($address->validationRaw()['issues'])) {
            $validationIssues = $address->validationRaw()['issues'];
        }

        return new AddressEvidenceSnapshotData(
            $this->newSnapshotId(),
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
            $createdAt,
        );
    }

    private function bindEvidenceSnapshot(\PDOStatement $pdoStatement, AddressEvidenceSnapshotInterface $addressEvidenceSnapshot): void
    {
        $pdoStatement->bindValue(':id', $addressEvidenceSnapshot->id());
        $pdoStatement->bindValue(':address_id', $addressEvidenceSnapshot->addressId());
        $pdoStatement->bindValue(':owner_id', $addressEvidenceSnapshot->ownerId());
        $pdoStatement->bindValue(':vendor_id', $addressEvidenceSnapshot->vendorId());
        $pdoStatement->bindValue(':source_system', $addressEvidenceSnapshot->sourceSystem());
        $pdoStatement->bindValue(':source_type', AddressRecordPolicy::normalizeSourceType($addressEvidenceSnapshot->sourceType()));
        $pdoStatement->bindValue(':source_reference', $addressEvidenceSnapshot->sourceReference());
        $pdoStatement->bindValue(':validated_by', $addressEvidenceSnapshot->validatedBy());
        $pdoStatement->bindValue(':validated_at', $addressEvidenceSnapshot->validatedAt());
        $pdoStatement->bindValue(':normalization_version', $addressEvidenceSnapshot->normalizationVersion());
        $pdoStatement->bindValue(':raw_input_snapshot', $this->encodeJsonNullable($addressEvidenceSnapshot->rawInputSnapshot()));
        $pdoStatement->bindValue(':normalized_snapshot', $this->encodeJsonNullable($addressEvidenceSnapshot->normalizedSnapshot()));
        $pdoStatement->bindValue(':validation_status', AddressRecordPolicy::normalizeValidationStatus($addressEvidenceSnapshot->validationStatus()));
        $pdoStatement->bindValue(':validation_score', $addressEvidenceSnapshot->validationScore());
        $pdoStatement->bindValue(':validation_issues', $this->encodeJsonNullable($addressEvidenceSnapshot->validationIssues()));
        $pdoStatement->bindValue(':provider_digest', $addressEvidenceSnapshot->providerDigest());
        $pdoStatement->bindValue(':created_at', $addressEvidenceSnapshot->createdAt());
    }

    /** @param array<string, mixed> $row */
    private function mapEvidenceSnapshot(array $row): AddressEvidenceSnapshotInterface
    {
        return new AddressEvidenceSnapshotData(
            $this->asString($row['id'] ?? null, 'id'),
            $this->asString($row['address_id'] ?? null, 'address_id'),
            $this->asNullableString($row['owner_id'] ?? null),
            $this->asNullableString($row['vendor_id'] ?? null),
            $this->asNullableString($row['source_system'] ?? null),
            AddressRecordPolicy::normalizeSourceType($this->asNullableString($row['source_type'] ?? null)),
            $this->asNullableString($row['source_reference'] ?? null),
            $this->asNullableString($row['validated_by'] ?? null),
            $this->asNullableString($row['validated_at'] ?? null),
            $this->asNullableString($row['normalization_version'] ?? null),
            $this->decodeJsonNullable($row['raw_input_snapshot'] ?? null),
            $this->decodeJsonNullable($row['normalized_snapshot'] ?? null),
            AddressRecordPolicy::normalizeValidationStatus($this->asString($row['validation_status'] ?? null, 'validation_status')),
            $this->asNullableInt($row['validation_score'] ?? null),
            $this->decodeJsonNullable($row['validation_issues'] ?? null),
            $this->asNullableString($row['provider_digest'] ?? null),
            $this->asString($row['created_at'] ?? null, 'created_at'),
        );
    }

    private function newSnapshotId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $exception) {
            throw new \RuntimeException('address_snapshot_id_failed', 0, $exception);
        }
    }

    private function encodeEvidenceCursor(string $createdAt, string $id): string
    {
        return base64_encode($createdAt.'
'.$id);
    }

    /** @return array{0: string, 1: string} */
    private function decodeEvidenceCursor(string $cursor): array
    {
        $decoded = base64_decode($cursor, true);
        if (false === $decoded || !str_contains($decoded, '
')) {
            throw new \RuntimeException('invalid_evidence_cursor');
        }

        [$createdAt, $id] = explode('
', $decoded, 2);

        return [$createdAt, $id];
    }

    /**
     * @return array{line1_norm: ?string, city_norm: ?string, region_norm: ?string, postal_code_norm: ?string}
     */
    private function normalizedFields(AddressInterface $address): array
    {
        return [
            'line1_norm' => $this->normalizeText($address->line1Norm(), $address->line1()),
            'city_norm' => $this->normalizeText($address->cityNorm(), $address->city()),
            'region_norm' => $this->normalizeText($address->regionNorm(), $address->region()),
            'postal_code_norm' => $this->normalizeText($address->postalCodeNorm(), $address->postalCode()),
        ];
    }

    /**
     * @param array{line1_norm: ?string, city_norm: ?string, region_norm: ?string, postal_code_norm: ?string} $normalized
     */
    private function effectiveDedupeKey(AddressInterface $address, array $normalized): ?string
    {
        $dedupeKey = $this->normalizeOptionalScalar($address->dedupeKey());
        if (null !== $dedupeKey) {
            return $dedupeKey;
        }

        if (
            null === $normalized['line1_norm']
            && null === $normalized['city_norm']
            && null === $normalized['region_norm']
            && null === $normalized['postal_code_norm']
        ) {
            return null;
        }

        return implode('|', [
            $normalized['line1_norm'] ?? '',
            $normalized['city_norm'] ?? '',
            $normalized['region_norm'] ?? '',
            $normalized['postal_code_norm'] ?? '',
            strtoupper(trim($address->countryCode())),
            $this->normalizeOptionalScalar($address->ownerId()) ?? '',
            $this->normalizeOptionalScalar($address->vendorId()) ?? '',
        ]);
    }

    /** @param array<string, mixed> $patch
     * @return array<string, mixed>
     */
    private function normalizeOperationalPatch(string $id, string $currentGovernanceStatus, array $patch): array
    {
        $normalized = [];

        if (array_key_exists('governanceStatus', $patch)) {
            $normalized = array_merge(
                $normalized,
                AddressGovernancePolicy::normalizePatch($currentGovernanceStatus, $id, $patch)
            );
        }

        if (array_key_exists('revalidationDueAt', $patch)) {
            $normalized['revalidation_due_at'] = $this->asNullableString($patch['revalidationDueAt'] ?? null);
        }
        if (array_key_exists('revalidationPolicy', $patch)) {
            $normalized['revalidation_policy'] = AddressRecordPolicy::normalizeRevalidationPolicy($this->asNullableString($patch['revalidationPolicy'] ?? null));
        }
        if (array_key_exists('lastValidationProvider', $patch)) {
            $normalized['last_validation_provider'] = $this->asNullableString($patch['lastValidationProvider'] ?? null);
        }
        if (array_key_exists('lastValidationStatus', $patch)) {
            $normalized['last_validation_status'] = AddressRecordPolicy::normalizeLastValidationStatus($this->asNullableString($patch['lastValidationStatus'] ?? null));
        }
        if (array_key_exists('lastValidationScore', $patch)) {
            $normalized['last_validation_score'] = $this->asNullableInt($patch['lastValidationScore'] ?? null);
        }

        return $normalized;
    }

    /** @param array<string, mixed> $normalized */
    private function assertGovTargetsExist(array $normalized, ?string $ownerId, ?string $vendorId): void
    {
        $targets = [
            $normalized['duplicate_of_id'] ?? null,
            $normalized['superseded_by_id'] ?? null,
            $normalized['alias_of_id'] ?? null,
            $normalized['conflict_with_id'] ?? null,
        ];

        foreach ($targets as $target) {
            if (!is_string($target)) {
                continue;
            }
            if ('' === trim($target)) {
                continue;
            }
            if (!$this->get($target, $ownerId, $vendorId) instanceof AddressInterface) {
                throw new \RuntimeException(sprintf('Governance link target "%s" was not found in the current tenant scope.', $target));
            }
        }
    }

    private function governanceLinkId(AddressInterface $address): ?string
    {
        return match (AddressRecordPolicy::normalizeGovernanceStatus($address->governanceStatus())) {
            'duplicate' => $address->duplicateOfId(),
            'superseded' => $address->supersededById(),
            'alias' => $address->aliasOfId(),
            'conflict' => $address->conflictWithId(),
            default => null,
        };
    }

    private function sanitizeGovernanceLink(?string $linkId, string $currentId): ?string
    {
        $linkId = $this->asNullableString($linkId);
        $currentId = $this->asNullableString($currentId);
        if (null === $linkId || null === $currentId || $linkId === $currentId) {
            return null;
        }

        return $linkId;
    }

    private function normalizeText(?string $normalized, ?string $raw): ?string
    {
        $value = $this->normalizeOptionalScalar($normalized);
        if (null !== $value) {
            return $value;
        }

        return $this->normalizeOptionalScalar($raw);
    }

    private function normalizeOptionalScalar(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        return strtolower(preg_replace('/\s+/', '', $value) ?? $value);
    }

    private function evidencePresenceClause(bool $hasEvidence): string
    {
        $clause = '(raw_input_snapshot IS NOT NULL OR normalized_snapshot IS NOT NULL OR provider_digest IS NOT NULL)';

        return $hasEvidence ? $clause : '(NOT '.$clause.')';
    }

    private function currentTimestampAtom(): string
    {
        $now = new \DateTimeImmutable();

        return $now->format(DATE_ATOM);
    }

    private function currentTimestampLiteral(): string
    {
        $now = new \DateTimeImmutable('now');

        return $now->format('Y-m-d H:i:sP');
    }

    private function currentTimestampSql(): string
    {
        return 'pgsql' === $this->databaseDriverName() ? 'now()' : 'CURRENT_TIMESTAMP';
    }

    /** @param array<string, mixed>|null $value */
    private function encodeJsonNullable(?array $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return false === $json ? null : $json;
    }

    /** @return array<string, mixed>|null */
    private function decodeJsonNullable(mixed $value): ?array
    {
        if (null === $value) {
            return null;
        }
        if (is_array($value)) {
            /* @var array<string, mixed> $value */
            return $value;
        }
        if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
            return null;
        }
        $jsonPayload = trim((string) $value);
        if ('' === $jsonPayload) {
            return null;
        }
        $decoded = json_decode($jsonPayload, true);
        if (!is_array($decoded)) {
            return null;
        }

        /* @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function asString(mixed $value, string $field): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }
        throw new \RuntimeException('invalid_'.$field);
    }

    private function asNullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return null;
    }

    private function asNullableFloat(mixed $value): ?float
    {
        if (null === $value) {
            return null;
        }
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function asNullableInt(mixed $value): ?int
    {
        if (null === $value) {
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

    private function asNullableBool(mixed $value): ?bool
    {
        if (null === $value) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return 1 === $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return ((int) $value) === 1;
        }

        return null;
    }

    /**
     * @param non-empty-string     $name
     * @param array<string, mixed> $payload
     */
    private function appendOutbox(string $name, array $payload = []): void
    {
        $payloadJson = $this->encodedOutboxPayload($name, $payload);
        $stmt = $this->prepare(
            'INSERT INTO address_outbox (event_name, event_version, payload) '
            .'VALUES (:name, :ver, '.$this->outboxPayloadExpression().')'
        );

        $stmt->execute([
            ':name' => $name,
            ':ver' => AddressOutboxEventContract::eventVersion($name),
            ':payload' => $payloadJson,
        ]);
    }

    /**
     * @param non-empty-string     $name
     * @param array<string, mixed> $payload
     */
    private function encodedOutboxPayload(string $name, array $payload): string
    {
        $decoratedPayload = AddressOutboxEventContract::decoratePayload($name, $payload);
        $payloadJson = json_encode(
            $decoratedPayload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if (false === $payloadJson) {
            throw new \RuntimeException('payload_encode_failed');
        }

        return $payloadJson;
    }

    private function outboxPayloadExpression(): string
    {
        return 'pgsql' === $this->databaseDriverName()
            ? ':payload::jsonb'
            : ':payload';
    }

    private function prepare(string $sql): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        if (false === $stmt) {
            throw new \RuntimeException('prepare_failed');
        }

        return $stmt;
    }

    /** @param array<string, mixed> $filters */
    private function stringFilter(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /** @param array<string, mixed> $filters */
    private function hasEvidenceFilter(array $filters): ?bool
    {
        $value = $filters['hasEvidence'] ?? null;
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no'], true)) {
                return false;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $row */
    private function stringRowValue(array $row, string $key, string $default = ''): string
    {
        $value = $row[$key] ?? null;
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $default;
    }

    /** @param array<string, mixed> $row */
    private function intRowValue(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function ensureTenantScope(?string $ownerId, ?string $vendorId): void
    {
        if (null === $ownerId && null === $vendorId) {
            throw new \RuntimeException('tenant_scope_required');
        }
    }

    private function tenantWhereClause(?string $ownerId, ?string $vendorId): string
    {
        $clauses = [];
        if (null !== $ownerId) {
            $clauses[] = 'owner_id = :owner_id';
        }
        if (null !== $vendorId) {
            $clauses[] = 'vendor_id = :vendor_id';
        }

        return '('.implode(' AND ', $clauses).')';
    }

    /**
     * @return array<string, string|null>
     */
    private function tenantParams(?string $ownerId, ?string $vendorId): array
    {
        $params = [];
        if (null !== $ownerId) {
            $params[':owner_id'] = $ownerId;
        }
        if (null !== $vendorId) {
            $params[':vendor_id'] = $vendorId;
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   normalizationVersion:string,
     *   validationStatus:string,
     *   'total':int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   'evidenceMissing':int,
     *   'dueForRevalidation':int,
     *   'uncertainValidation':int,
     *   staleNormalization:int
     * }>
     */
    #[\Override]
    public function summarizeNormalizationPortfolio(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, array $filters = []): array
    {
        $params = $this->tenantParams($ownerId, $vendorId);
        $where = $this->buildScopedSearchWhere($ownerId, $vendorId, $countryCode, $q, $params);
        $this->applyPortfolioFilters($where, $params, $filters, true, true);

        $expectedNormalizationVersion = $this->stringFilter($filters, 'expectedNormalizationVersion');
        $this->summaryDueBefore($params, $filters);
        if (null !== $expectedNormalizationVersion) {
            $params[':expected_normalization_version'] = $expectedNormalizationVersion;
        }

        $sql = 'SELECT COALESCE(normalization_version, "") AS normalization_version, '
            .'COALESCE(last_validation_status, validation_status, "unknown") AS validation_status, '
            .'COUNT(*) AS total, '
            ."SUM(CASE WHEN governance_status = 'canonical' THEN 1 ELSE 0 END) AS canonical_count, "
            ."SUM(CASE WHEN governance_status = 'duplicate' THEN 1 ELSE 0 END) AS duplicate_count, "
            ."SUM(CASE WHEN governance_status = 'superseded' THEN 1 ELSE 0 END) AS superseded_count, "
            ."SUM(CASE WHEN governance_status = 'alias' THEN 1 ELSE 0 END) AS alias_count, "
            ."SUM(CASE WHEN governance_status = 'conflict' THEN 1 ELSE 0 END) AS conflict_count, "
            .'SUM(CASE WHEN '.$this->evidencePresenceClause(true).' THEN 1 ELSE 0 END) AS evidence_backed_count, '
            .'SUM(CASE WHEN '.$this->evidencePresenceClause(false).' THEN 1 ELSE 0 END) AS evidence_missing_count, '
            .'SUM(CASE WHEN revalidation_due_at IS NOT NULL AND revalidation_due_at <= :summary_due_before THEN 1 ELSE 0 END) AS due_for_revalidation_count, '
            ."SUM(CASE WHEN validation_status = 'uncertain' OR last_validation_status = 'uncertain' THEN 1 ELSE 0 END) AS uncertain_validation_count, "
            .'SUM(CASE WHEN :expected_normalization_version IS NOT NULL AND COALESCE(normalization_version, "") <> :expected_normalization_version THEN 1 ELSE 0 END) AS stale_normalization_count '
            .'FROM address_entity WHERE '.implode(' AND ', $where)
            .' GROUP BY COALESCE(normalization_version, ""), COALESCE(last_validation_status, validation_status, "unknown")'
            .' ORDER BY stale_normalization_count ASC, total DESC, normalization_version ASC, '
            ."CASE COALESCE(last_validation_status, validation_status, 'unknown') "
            ."WHEN 'uncertain' THEN 0 "
            ."WHEN 'validated' THEN 1 "
            ."WHEN 'rejected' THEN 2 "
            ."WHEN 'pending' THEN 3 "
            .'ELSE 4 END ASC';

        $pdoStatement = $this->prepare($sql);
        $this->bindStatementValues($pdoStatement, $params);
        if (null === $expectedNormalizationVersion) {
            $pdoStatement->bindValue(':expected_normalization_version', null, \PDO::PARAM_NULL);
        }
        $pdoStatement->execute();
        $rows = $this->fetchAssocRows($pdoStatement);

        return array_map(fn (array $row): array => [
            'normalizationVersion' => $this->stringRowValue($row, 'normalization_version'),
            'validationStatus' => $this->stringRowValue($row, 'validation_status', 'unknown'),
            'total' => $this->intRowValue($row, 'total'),
            'canonical' => $this->intRowValue($row, 'canonical_count'),
            'duplicate' => $this->intRowValue($row, 'duplicate_count'),
            'superseded' => $this->intRowValue($row, 'superseded_count'),
            'alias' => $this->intRowValue($row, 'alias_count'),
            'conflict' => $this->intRowValue($row, 'conflict_count'),
            'evidenceBacked' => $this->intRowValue($row, 'evidence_backed_count'),
            'evidenceMissing' => $this->intRowValue($row, 'evidence_missing_count'),
            'dueForRevalidation' => $this->intRowValue($row, 'due_for_revalidation_count'),
            'uncertainValidation' => $this->intRowValue($row, 'uncertain_validation_count'),
            'staleNormalization' => $this->intRowValue($row, 'stale_normalization_count'),
        ], $rows);
    }
}
