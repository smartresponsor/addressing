<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 */
declare(strict_types=1);

namespace App\Repository\Persistence\Address;

use App\Contract\Message\Address\AddressOutboxEventContract;
use App\Contract\Message\Address\AddressRecordPolicy;
use App\Entity\Record\Address\AddressData;
use App\Entity\Record\Address\AddressEvidenceSnapshotData;
use App\EntityInterface\Record\Address\AddressEvidenceSnapshotInterface;
use App\EntityInterface\Record\Address\AddressInterface;
use App\RepositoryInterface\Persistence\Address\AddressRepositoryInterface;
use App\Service\Application\Address\AddressGovernancePolicy;

final readonly class AddressRepository implements AddressRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

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
                'updatedAt' => $address->updatedAt() ?? (new \DateTimeImmutable())->format(DATE_ATOM),
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

    public function appendEvidenceSnapshot(AddressInterface $address): ?AddressEvidenceSnapshotInterface
    {
        if (!$this->hasEvidence($address)) {
            return null;
        }

        $snapshot = $this->buildEvidenceSnapshot($address);
        $stmt = $this->prepare(<<<'SQL'
INSERT INTO address_evidence_snapshot
    (id, address_id, owner_id, vendor_id, source_system, source_type, source_reference, validated_by, validated_at,
     normalization_version, raw_input_snapshot, normalized_snapshot, validation_status, validation_score, validation_issues, provider_digest, created_at)
VALUES
    (:id, :address_id, :owner_id, :vendor_id, :source_system, :source_type, :source_reference, :validated_by, :validated_at,
     :normalization_version, :raw_input_snapshot, :normalized_snapshot, :validation_status, :validation_score, :validation_issues, :provider_digest, :created_at)
SQL
        );
        $this->bindEvidenceSnapshot($stmt, $snapshot);
        $stmt->execute();

        return $snapshot;
    }

    public function getLatestEvidenceSnapshot(string $addressId, ?string $ownerId, ?string $vendorId): ?AddressEvidenceSnapshotInterface
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $params = array_merge([':address_id' => $addressId], $this->tenantParams($ownerId, $vendorId));
        $stmt = $this->prepare(
            'SELECT * FROM address_evidence_snapshot WHERE address_id = :address_id AND '.$this->tenantWhereClause($ownerId, $vendorId)
            .' ORDER BY created_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $this->mapEvidenceSnapshot($row) : null;
    }

    /**
     * @return array{items: list<AddressEvidenceSnapshotInterface>, nextCursor: ?string}
     */
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
            .' ORDER BY created_at DESC, id DESC LIMIT :limit';
        $stmt = $this->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $safeRows = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $safeRows[] = $row;
                }
            }
        }

        $items = array_map(fn (array $row): AddressEvidenceSnapshotInterface => $this->mapEvidenceSnapshot($row), $safeRows);

        $nextCursor = null;
        if (count($safeRows) === $limit && [] !== $safeRows) {
            $last = end($safeRows);
            if (is_array($last)) {
                $nextCursor = $this->encodeEvidenceCursor((string) ($last['created_at'] ?? ''), (string) ($last['id'] ?? ''));
            }
        }

        return ['items' => $items, 'nextCursor' => $nextCursor];
    }

    public function get(string $id, ?string $ownerId, ?string $vendorId): ?AddressInterface
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $params = array_merge([':id' => $id], $this->tenantParams($ownerId, $vendorId));
        $stmt = $this->prepare(
            'SELECT * FROM address_entity WHERE id=:id AND deleted_at IS NULL AND '
            .$this->tenantWhereClause($ownerId, $vendorId)
        );
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        /* @var array<string, mixed> $row */
        return $this->map($row);
    }

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
                'deletedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function findByDedupeKey(string $dedupeKey): ?AddressInterface
    {
        $dedupeKey = trim($dedupeKey);
        if ('' === $dedupeKey) {
            return null;
        }

        $stmt = $this->prepare('SELECT * FROM address_entity WHERE dedupe_key = :dedupe AND deleted_at IS NULL');
        $stmt->execute([':dedupe' => $dedupeKey]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        /* @var array<string, mixed> $row */
        return $this->map($row);
    }

    public function markDeleted(string $id, ?string $ownerId, ?string $vendorId): void
    {
        $this->delete($id, $ownerId, $vendorId);
    }

    /**
     * @param array<string, mixed> $patch
     */
    public function patchOperational(string $id, ?string $ownerId, ?string $vendorId, array $patch): bool
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $current = $this->get($id, $ownerId, $vendorId);
        if (null === $current) {
            return false;
        }

        $normalized = $this->normalizeOperationalPatch($current->id(), $current->governanceStatus(), $patch);
        if ([] === $normalized) {
            return false;
        }

        $this->assertOperationalGovernanceTargetsExist($normalized, $ownerId, $vendorId);

        $tenantWhere = $this->tenantWhereClause($ownerId, $vendorId);
        $params = array_merge([':id' => $id], $this->tenantParams($ownerId, $vendorId));
        $set = [];
        foreach ($normalized as $column => $value) {
            $placeholder = ':'.$column;
            $set[] = $column.' = '.$placeholder;
            $params[$placeholder] = $value;
        }
        $set[] = 'updated_at = :updated_at';
        $params[':updated_at'] = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');

        $this->pdo->beginTransaction();
        try {
            $sql = 'UPDATE address_entity SET '.implode(', ', $set).' WHERE id = :id AND deleted_at IS NULL AND '.$tenantWhere;
            $stmt = $this->prepare($sql);
            $stmt->execute($params);
            if (0 === $stmt->rowCount()) {
                $this->pdo->rollBack();

                return false;
            }

            $governanceStatus = array_key_exists('governance_status', $normalized)
                ? (string) $normalized['governance_status']
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
     * @return array{items: AddressInterface[], nextCursor: ?string}
     */
    public function findPage(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, int $limit, ?string $cursor, array $filters = []): array
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $limit = max(1, min(200, $limit));
        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driverAttr) ? $driverAttr : '';
        $params = $this->tenantParams($ownerId, $vendorId);
        $where = ['deleted_at IS NULL', $this->tenantWhereClause($ownerId, $vendorId)];
        if ($countryCode) {
            $where[] = 'country_code = :country_code';
            $params[':country_code'] = $countryCode;
        }
        if ($q) {
            $op = 'pgsql' === $driver ? 'ILIKE' : 'LIKE';
            $where[] = "lower(line1 || ' ' || city || ' ' || coalesce(postal_code,'')) {$op} lower(:q)";
            $params[':q'] = '%'.$q.'%';
        }
        if ($cursor) {
            $where[] = 'id > :cursor';
            $params[':cursor'] = $cursor;
        }

        $sourceType = AddressRecordPolicy::normalizeSourceType($this->stringFilter($filters, 'sourceType'));
        if (null !== $sourceType) {
            $where[] = 'source_type = :source_type';
            $params[':source_type'] = $sourceType;
        }

        $governanceStatus = AddressRecordPolicy::normalizeGovernanceStatus($this->stringFilter($filters, 'governanceStatus'));
        if ('canonical' !== $governanceStatus || null !== $this->stringFilter($filters, 'governanceStatus')) {
            if (null !== $this->stringFilter($filters, 'governanceStatus')) {
                $where[] = 'governance_status = :governance_status';
                $params[':governance_status'] = $governanceStatus;
            }
        }

        $revalidationPolicy = AddressRecordPolicy::normalizeRevalidationPolicy($this->stringFilter($filters, 'revalidationPolicy'));
        if (null !== $revalidationPolicy) {
            $where[] = 'revalidation_policy = :revalidation_policy';
            $params[':revalidation_policy'] = $revalidationPolicy;
        }

        $hasEvidence = $this->boolFilter($filters, 'hasEvidence');
        if (true === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(true);
        } elseif (false === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(false);
        }

        $revalidationDueBefore = $this->stringFilter($filters, 'revalidationDueBefore');
        if (null !== $revalidationDueBefore) {
            $where[] = 'revalidation_due_at IS NOT NULL AND revalidation_due_at <= :revalidation_due_before';
            $params[':revalidation_due_before'] = $revalidationDueBefore;
        }

        $queue = $this->stringFilter($filters, 'queue');
        $expectedNormalizationVersion = $this->stringFilter($filters, 'expectedNormalizationVersion');
        if (null !== $queue) {
            switch ($queue) {
                case 'dueForRevalidation':
                    $dueAt = $revalidationDueBefore ?? $this->currentTimestampLiteral();
                    $where[] = 'revalidation_due_at IS NOT NULL AND revalidation_due_at <= :queue_due_before';
                    $params[':queue_due_before'] = $dueAt;
                    break;
                case 'evidenceMissing':
                    $where[] = $this->evidencePresenceClause(false);
                    break;
                case 'uncertainValidation':
                    $where[] = '(validation_status = :queue_validation_status OR last_validation_status = :queue_last_validation_status)';
                    $params[':queue_validation_status'] = 'uncertain';
                    $params[':queue_last_validation_status'] = 'uncertain';
                    break;
                case 'conflictReview':
                    $where[] = 'governance_status = :queue_governance_conflict';
                    $params[':queue_governance_conflict'] = 'conflict';
                    break;
                case 'duplicateReview':
                    $where[] = 'governance_status = :queue_governance_duplicate';
                    $params[':queue_governance_duplicate'] = 'duplicate';
                    break;
                case 'staleNormalizationVersion':
                    if (null !== $expectedNormalizationVersion) {
                        $where[] = '(normalization_version IS NULL OR normalization_version <> :expected_normalization_version)';
                        $params[':expected_normalization_version'] = $expectedNormalizationVersion;
                    }
                    break;
            }
        }

        $sql = 'SELECT * FROM address_entity WHERE '.implode(' AND ', $where).' ORDER BY id ASC LIMIT :limit';
        $stmt = $this->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        /** @var array<int, array<string, mixed>> $safeRows */
        $safeRows = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row)) {
                    /* @var array<string, mixed> $row */
                    $safeRows[] = $row;
                }
            }
        }
        $items = array_map(fn (array $r): AddressInterface => $this->map($r), $safeRows);

        $nextCursor = null;
        if (count($safeRows) === $limit && [] !== $safeRows) {
            $last = end($safeRows);
            if (is_array($last) && isset($last['id'])) {
                $nextCursor = (string) $last['id'];
            }
        }

        return ['items' => $items, 'nextCursor' => $nextCursor];
    }

    /**
     * @return array{
     *   addressId:string,
     *   governanceStatus:?string,
     *   primaryLinkId:?string,
     *   linkedToAnother:bool,
     *   duplicateChildren:int,
     *   supersededChildren:int,
     *   aliasChildren:int,
     *   conflictPeers:int,
     *   inboundLinkedTotal:int,
     *   clusterSize:int,
     *   relatedAddressIds:list<string>
     * }
     */
    public function summarizeGovernanceCluster(string $addressId, ?string $ownerId, ?string $vendorId): array
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $current = $this->get($addressId, $ownerId, $vendorId);
        if (null === $current) {
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
        $stmt = $this->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $relatedIds = [];
        $primaryLinkId = $this->governanceLinkId($current);
        if (null !== $primaryLinkId) {
            $relatedIds[] = $primaryLinkId;
        }

        $listSql = 'SELECT id FROM address_entity WHERE deleted_at IS NULL AND '.$tenantWhere
            .' AND (duplicate_of_id = :address_id OR superseded_by_id = :address_id OR alias_of_id = :address_id OR conflict_with_id = :address_id) ORDER BY id ASC';
        $listStmt = $this->prepare($listSql);
        foreach ($params as $k => $v) {
            $listStmt->bindValue($k, $v);
        }
        $listStmt->execute();
        while (($id = $listStmt->fetchColumn()) !== false) {
            if (is_string($id) && '' !== $id) {
                $relatedIds[] = $id;
            }
        }

        $relatedIds = array_values(array_unique($relatedIds));
        $duplicateChildren = (int) ($row['duplicate_children'] ?? 0);
        $supersededChildren = (int) ($row['superseded_children'] ?? 0);
        $aliasChildren = (int) ($row['alias_children'] ?? 0);
        $conflictPeers = (int) ($row['conflict_peers'] ?? 0);
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
     * @return array{
     *   total:int,
     *   dueForRevalidation:int,
     *   evidenceMissing:int,
     *   uncertainValidation:int,
     *   conflictReview:int,
     *   duplicateReview:int,
     *   staleNormalizationVersion:int
     * }
     */
    public function summarizeOperationalQueues(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, array $filters = []): array
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driverAttr) ? $driverAttr : '';
        $params = $this->tenantParams($ownerId, $vendorId);
        $where = ['deleted_at IS NULL', $this->tenantWhereClause($ownerId, $vendorId)];
        if ($countryCode) {
            $where[] = 'country_code = :country_code';
            $params[':country_code'] = $countryCode;
        }
        if ($q) {
            $op = 'pgsql' === $driver ? 'ILIKE' : 'LIKE';
            $where[] = "lower(line1 || ' ' || city || ' ' || coalesce(postal_code,'')) {$op} lower(:q)";
            $params[':q'] = '%'.$q.'%';
        }

        $sourceType = AddressRecordPolicy::normalizeSourceType($this->stringFilter($filters, 'sourceType'));
        if (null !== $sourceType) {
            $where[] = 'source_type = :source_type';
            $params[':source_type'] = $sourceType;
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

        $hasEvidence = $this->boolFilter($filters, 'hasEvidence');
        if (true === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(true);
        } elseif (false === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(false);
        }

        $revalidationDueBefore = $this->stringFilter($filters, 'revalidationDueBefore');
        if (null !== $revalidationDueBefore) {
            $where[] = 'revalidation_due_at IS NOT NULL AND revalidation_due_at <= :revalidation_due_before';
            $params[':revalidation_due_before'] = $revalidationDueBefore;
        }

        $expectedNormalizationVersion = $this->stringFilter($filters, 'expectedNormalizationVersion');
        $now = $revalidationDueBefore ?? $this->currentTimestampLiteral();
        $baseWhere = implode(' AND ', $where);
        $staleSql = null !== $expectedNormalizationVersion
            ? 'SUM(CASE WHEN normalization_version IS NULL OR normalization_version <> :expected_normalization_version THEN 1 ELSE 0 END)'
            : '0';
        if (null !== $expectedNormalizationVersion) {
            $params[':expected_normalization_version'] = $expectedNormalizationVersion;
        }

        $sql = 'SELECT '
            .'COUNT(*) AS total, '
            .'SUM(CASE WHEN revalidation_due_at IS NOT NULL AND revalidation_due_at <= :summary_due_before THEN 1 ELSE 0 END) AS due_for_revalidation, '
            .'SUM(CASE WHEN '.$this->evidencePresenceClause(false).' THEN 1 ELSE 0 END) AS evidence_missing, '
            ."SUM(CASE WHEN validation_status = 'uncertain' OR last_validation_status = 'uncertain' THEN 1 ELSE 0 END) AS uncertain_validation, "
            ."SUM(CASE WHEN governance_status = 'conflict' THEN 1 ELSE 0 END) AS conflict_review, "
            ."SUM(CASE WHEN governance_status = 'duplicate' THEN 1 ELSE 0 END) AS duplicate_review, "
            .$staleSql.' AS stale_normalization_version '
            .'FROM address_entity WHERE '.$baseWhere;

        $params[':summary_due_before'] = $now;
        $stmt = $this->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
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
            'total' => (int) ($row['total'] ?? 0),
            'dueForRevalidation' => (int) ($row['due_for_revalidation'] ?? 0),
            'evidenceMissing' => (int) ($row['evidence_missing'] ?? 0),
            'uncertainValidation' => (int) ($row['uncertain_validation'] ?? 0),
            'conflictReview' => (int) ($row['conflict_review'] ?? 0),
            'duplicateReview' => (int) ($row['duplicate_review'] ?? 0),
            'staleNormalizationVersion' => (int) ($row['stale_normalization_version'] ?? 0),
        ];
    }

    /**
     * @return list<array{
     *   countryCode:string,
     *   total:int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   evidenceMissing:int,
     *   dueForRevalidation:int,
     *   uncertainValidation:int
     * }>
     */
    public function summarizeCountryPortfolio(?string $ownerId, ?string $vendorId, ?string $q, array $filters = []): array
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driverAttr) ? $driverAttr : '';
        $params = $this->tenantParams($ownerId, $vendorId);
        $where = ['deleted_at IS NULL', $this->tenantWhereClause($ownerId, $vendorId)];
        if ($q) {
            $op = 'pgsql' === $driver ? 'ILIKE' : 'LIKE';
            $where[] = "lower(line1 || ' ' || city || ' ' || coalesce(postal_code,'')) {$op} lower(:q)";
            $params[':q'] = '%'.$q.'%';
        }

        $sourceType = AddressRecordPolicy::normalizeSourceType($this->stringFilter($filters, 'sourceType'));
        if (null !== $sourceType) {
            $where[] = 'source_type = :source_type';
            $params[':source_type'] = $sourceType;
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

        $hasEvidence = $this->boolFilter($filters, 'hasEvidence');
        if (true === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(true);
        } elseif (false === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(false);
        }

        $revalidationDueBefore = $this->stringFilter($filters, 'revalidationDueBefore');
        $params[':summary_due_before'] = $revalidationDueBefore ?? $this->currentTimestampLiteral();

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

        $stmt = $this->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $row): array => [
            'countryCode' => (string) ($row['country_code'] ?? ''),
            'total' => (int) ($row['total'] ?? 0),
            'canonical' => (int) ($row['canonical_count'] ?? 0),
            'duplicate' => (int) ($row['duplicate_count'] ?? 0),
            'superseded' => (int) ($row['superseded_count'] ?? 0),
            'alias' => (int) ($row['alias_count'] ?? 0),
            'conflict' => (int) ($row['conflict_count'] ?? 0),
            'evidenceBacked' => (int) ($row['evidence_backed_count'] ?? 0),
            'evidenceMissing' => (int) ($row['evidence_missing_count'] ?? 0),
            'dueForRevalidation' => (int) ($row['due_for_revalidation_count'] ?? 0),
            'uncertainValidation' => (int) ($row['uncertain_validation_count'] ?? 0),
        ], $rows);
    }

    /**
     * @return list<array{
     *   sourceSystem:string,
     *   sourceType:string,
     *   total:int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   evidenceMissing:int,
     *   dueForRevalidation:int,
     *   uncertainValidation:int
     * }>
     */
    public function summarizeSourcePortfolio(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, array $filters = []): array
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driverAttr) ? $driverAttr : '';
        $params = $this->tenantParams($ownerId, $vendorId);
        $where = ['deleted_at IS NULL', $this->tenantWhereClause($ownerId, $vendorId)];
        if ($countryCode) {
            $where[] = 'country_code = :country_code';
            $params[':country_code'] = $countryCode;
        }
        if ($q) {
            $op = 'pgsql' === $driver ? 'ILIKE' : 'LIKE';
            $where[] = "lower(line1 || ' ' || city || ' ' || coalesce(postal_code,'')) {$op} lower(:q)";
            $params[':q'] = '%'.$q.'%';
        }

        $sourceType = AddressRecordPolicy::normalizeSourceType($this->stringFilter($filters, 'sourceType'));
        if (null !== $sourceType) {
            $where[] = 'source_type = :source_type';
            $params[':source_type'] = $sourceType;
        }

        $sourceSystem = $this->stringFilter($filters, 'sourceSystem');
        if (null !== $sourceSystem) {
            $where[] = 'source_system = :source_system';
            $params[':source_system'] = $sourceSystem;
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

        $hasEvidence = $this->boolFilter($filters, 'hasEvidence');
        if (true === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(true);
        } elseif (false === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(false);
        }

        $revalidationDueBefore = $this->stringFilter($filters, 'revalidationDueBefore');
        $params[':summary_due_before'] = $revalidationDueBefore ?? $this->currentTimestampLiteral();

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

        $stmt = $this->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $row): array => [
            'sourceSystem' => (string) ($row['source_system'] ?? ''),
            'sourceType' => (string) ($row['source_type'] ?? ''),
            'total' => (int) ($row['total'] ?? 0),
            'canonical' => (int) ($row['canonical_count'] ?? 0),
            'duplicate' => (int) ($row['duplicate_count'] ?? 0),
            'superseded' => (int) ($row['superseded_count'] ?? 0),
            'alias' => (int) ($row['alias_count'] ?? 0),
            'conflict' => (int) ($row['conflict_count'] ?? 0),
            'evidenceBacked' => (int) ($row['evidence_backed_count'] ?? 0),
            'evidenceMissing' => (int) ($row['evidence_missing_count'] ?? 0),
            'dueForRevalidation' => (int) ($row['due_for_revalidation_count'] ?? 0),
            'uncertainValidation' => (int) ($row['uncertain_validation_count'] ?? 0),
        ], $rows);
    }

    /**
     * @return list<array{
     *   validationProvider:string,
     *   validationStatus:string,
     *   total:int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   evidenceMissing:int,
     *   dueForRevalidation:int,
     *   uncertainValidation:int
     * }>
     */
    public function summarizeValidationPortfolio(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, array $filters = []): array
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driverAttr) ? $driverAttr : '';
        $params = $this->tenantParams($ownerId, $vendorId);
        $where = ['deleted_at IS NULL', $this->tenantWhereClause($ownerId, $vendorId)];
        if ($countryCode) {
            $where[] = 'country_code = :country_code';
            $params[':country_code'] = $countryCode;
        }
        if ($q) {
            $op = 'pgsql' === $driver ? 'ILIKE' : 'LIKE';
            $where[] = "lower(line1 || ' ' || city || ' ' || coalesce(postal_code,'')) {$op} lower(:q)";
            $params[':q'] = '%'.$q.'%';
        }

        $sourceType = AddressRecordPolicy::normalizeSourceType($this->stringFilter($filters, 'sourceType'));
        if (null !== $sourceType) {
            $where[] = 'source_type = :source_type';
            $params[':source_type'] = $sourceType;
        }

        $sourceSystem = $this->stringFilter($filters, 'sourceSystem');
        if (null !== $sourceSystem) {
            $where[] = 'source_system = :source_system';
            $params[':source_system'] = $sourceSystem;
        }

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

        $hasEvidence = $this->boolFilter($filters, 'hasEvidence');
        if (true === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(true);
        } elseif (false === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(false);
        }

        $revalidationDueBefore = $this->stringFilter($filters, 'revalidationDueBefore');
        $params[':summary_due_before'] = $revalidationDueBefore ?? $this->currentTimestampLiteral();

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

        $stmt = $this->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $row): array => [
            'validationProvider' => (string) ($row['validation_provider'] ?? ''),
            'validationStatus' => (string) ($row['validation_status'] ?? 'unknown'),
            'total' => (int) ($row['total'] ?? 0),
            'canonical' => (int) ($row['canonical_count'] ?? 0),
            'duplicate' => (int) ($row['duplicate_count'] ?? 0),
            'superseded' => (int) ($row['superseded_count'] ?? 0),
            'alias' => (int) ($row['alias_count'] ?? 0),
            'conflict' => (int) ($row['conflict_count'] ?? 0),
            'evidenceBacked' => (int) ($row['evidence_backed_count'] ?? 0),
            'evidenceMissing' => (int) ($row['evidence_missing_count'] ?? 0),
            'dueForRevalidation' => (int) ($row['due_for_revalidation_count'] ?? 0),
            'uncertainValidation' => (int) ($row['uncertain_validation_count'] ?? 0),
        ], $rows);
    }

    private function bindForCreate(\PDOStatement $stmt, AddressInterface $a): void
    {
        $normalized = $this->normalizedFields($a);
        $this->bindCommonValues($stmt, $a, $normalized);
        $stmt->bindValue(':created_at', $a->createdAt());
        $stmt->bindValue(':updated_at', $a->updatedAt());
        $stmt->bindValue(':deleted_at', $a->deletedAt());
    }

    private function bindForUpdate(\PDOStatement $stmt, AddressInterface $a): void
    {
        $normalized = $this->normalizedFields($a);
        $this->bindCommonValues($stmt, $a, $normalized);
        $stmt->bindValue(':updated_at', $a->updatedAt());
        $stmt->bindValue(':deleted_at', $a->deletedAt());
    }

    /**
     * @param array{line1_norm: ?string, city_norm: ?string, region_norm: ?string, postal_code_norm: ?string} $normalized
     */
    private function bindCommonValues(\PDOStatement $stmt, AddressInterface $a, array $normalized): void
    {
        $stmt->bindValue(':id', $a->id());
        $stmt->bindValue(':owner_id', $a->ownerId());
        $stmt->bindValue(':vendor_id', $a->vendorId());
        $stmt->bindValue(':line1', $a->line1());
        $stmt->bindValue(':line2', $a->line2());
        $stmt->bindValue(':city', $a->city());
        $stmt->bindValue(':region', $a->region());
        $stmt->bindValue(':postal_code', $a->postalCode());
        $stmt->bindValue(':country_code', $a->countryCode());
        $stmt->bindValue(':line1_norm', $normalized['line1_norm']);
        $stmt->bindValue(':city_norm', $normalized['city_norm']);
        $stmt->bindValue(':region_norm', $normalized['region_norm']);
        $stmt->bindValue(':postal_code_norm', $normalized['postal_code_norm']);
        $stmt->bindValue(':latitude', $a->latitude());
        $stmt->bindValue(':longitude', $a->longitude());
        $stmt->bindValue(':geohash', $a->geohash());
        $stmt->bindValue(':validation_status', AddressRecordPolicy::normalizeValidationStatus($a->validationStatus()));
        $stmt->bindValue(':validation_provider', $a->validationProvider());
        $stmt->bindValue(':validated_at', $a->validatedAt());
        $stmt->bindValue(':dedupe_key', $this->effectiveDedupeKey($a, $normalized));
        $stmt->bindValue(':validation_fingerprint', $a->validationFingerprint());
        $stmt->bindValue(':validation_raw', $this->encodeJsonNullable($a->validationRaw()));
        $stmt->bindValue(':validation_verdict', $this->encodeJsonNullable($a->validationVerdict()));
        $deliverable = $a->validationDeliverable();
        $stmt->bindValue(':validation_deliverable', null === $deliverable ? null : (int) $deliverable);
        $stmt->bindValue(':validation_granularity', $a->validationGranularity());
        $stmt->bindValue(':validation_quality', $a->validationQuality());
        $stmt->bindValue(':source_system', $a->sourceSystem());
        $stmt->bindValue(':source_type', AddressRecordPolicy::normalizeSourceType($a->sourceType()));
        $stmt->bindValue(':source_reference', $a->sourceReference());
        $stmt->bindValue(':normalization_version', $a->normalizationVersion());
        $stmt->bindValue(':raw_input_snapshot', $this->encodeJsonNullable($a->rawInputSnapshot()));
        $stmt->bindValue(':normalized_snapshot', $this->encodeJsonNullable($a->normalizedSnapshot()));
        $stmt->bindValue(':provider_digest', $a->providerDigest());
        $stmt->bindValue(':governance_status', AddressRecordPolicy::normalizeGovernanceStatus($a->governanceStatus()));
        $stmt->bindValue(':duplicate_of_id', $this->sanitizeGovernanceLink($a->duplicateOfId(), $a->id()));
        $stmt->bindValue(':superseded_by_id', $this->sanitizeGovernanceLink($a->supersededById(), $a->id()));
        $stmt->bindValue(':alias_of_id', $this->sanitizeGovernanceLink($a->aliasOfId(), $a->id()));
        $stmt->bindValue(':conflict_with_id', $this->sanitizeGovernanceLink($a->conflictWithId(), $a->id()));
        $stmt->bindValue(':revalidation_due_at', $a->revalidationDueAt());
        $stmt->bindValue(':revalidation_policy', AddressRecordPolicy::normalizeRevalidationPolicy($a->revalidationPolicy()));
        $stmt->bindValue(':last_validation_provider', $a->lastValidationProvider());
        $stmt->bindValue(':last_validation_status', AddressRecordPolicy::normalizeLastValidationStatus($a->lastValidationStatus()));
        $stmt->bindValue(':last_validation_score', $a->lastValidationScore());
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
        return null !== $address->rawInputSnapshot()
            || null !== $address->normalizedSnapshot()
            || null !== $address->providerDigest()
            || null !== $address->validationRaw()
            || null !== $address->validationVerdict();
    }

    private function buildEvidenceSnapshot(AddressInterface $address): AddressEvidenceSnapshotInterface
    {
        $createdAt = $address->validatedAt() ?? $address->updatedAt() ?? $address->createdAt();
        $validatedBy = $address->validationProvider() ?? $address->lastValidationProvider();
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

    private function bindEvidenceSnapshot(\PDOStatement $stmt, AddressEvidenceSnapshotInterface $snapshot): void
    {
        $stmt->bindValue(':id', $snapshot->id());
        $stmt->bindValue(':address_id', $snapshot->addressId());
        $stmt->bindValue(':owner_id', $snapshot->ownerId());
        $stmt->bindValue(':vendor_id', $snapshot->vendorId());
        $stmt->bindValue(':source_system', $snapshot->sourceSystem());
        $stmt->bindValue(':source_type', AddressRecordPolicy::normalizeSourceType($snapshot->sourceType()));
        $stmt->bindValue(':source_reference', $snapshot->sourceReference());
        $stmt->bindValue(':validated_by', $snapshot->validatedBy());
        $stmt->bindValue(':validated_at', $snapshot->validatedAt());
        $stmt->bindValue(':normalization_version', $snapshot->normalizationVersion());
        $stmt->bindValue(':raw_input_snapshot', $this->encodeJsonNullable($snapshot->rawInputSnapshot()));
        $stmt->bindValue(':normalized_snapshot', $this->encodeJsonNullable($snapshot->normalizedSnapshot()));
        $stmt->bindValue(':validation_status', AddressRecordPolicy::normalizeValidationStatus($snapshot->validationStatus()));
        $stmt->bindValue(':validation_score', $snapshot->validationScore());
        $stmt->bindValue(':validation_issues', $this->encodeJsonNullable($snapshot->validationIssues()));
        $stmt->bindValue(':provider_digest', $snapshot->providerDigest());
        $stmt->bindValue(':created_at', $snapshot->createdAt());
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
        return bin2hex(random_bytes(16));
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

    private function assertOperationalGovernanceTargetsExist(array $normalized, ?string $ownerId, ?string $vendorId): void
    {
        $targets = [
            $normalized['duplicate_of_id'] ?? null,
            $normalized['superseded_by_id'] ?? null,
            $normalized['alias_of_id'] ?? null,
            $normalized['conflict_with_id'] ?? null,
        ];

        foreach ($targets as $targetId) {
            if (!is_string($targetId) || '' === trim($targetId)) {
                continue;
            }

            if (null === $this->get($targetId, $ownerId, $vendorId)) {
                throw new \RuntimeException(sprintf('Governance link target "%s" was not found in the current tenant scope.', $targetId));
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

    private function currentTimestampSql(): string
    {
        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driverAttr) ? $driverAttr : '';

        return 'pgsql' === $driver ? 'now()' : 'CURRENT_TIMESTAMP';
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
    private function decodeJsonNullable(mixed $v): ?array
    {
        if (null === $v) {
            return null;
        }
        if (is_array($v)) {
            /* @var array<string, mixed> $v */
            return $v;
        }
        if (!is_string($v) && !is_int($v) && !is_float($v) && !is_bool($v)) {
            return null;
        }
        $s = (string) $v;
        $s = trim($s);
        if ('' === $s) {
            return null;
        }
        $decoded = json_decode($s, true);
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
     * @param non-empty-string $name
     * @param array<mixed>     $payload
     */
    private function appendOutbox(string $name, array $payload = []): void
    {
        $payload = AddressOutboxEventContract::decoratePayload($name, $payload);
        $payloadJson = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if (false === $payloadJson) {
            throw new \RuntimeException('payload_encode_failed');
        }

        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driverAttr) ? $driverAttr : '';

        $payloadExpr = 'pgsql' === $driver
            ? ':payload::jsonb'
            : ':payload';

        $sql = "
        INSERT INTO address_outbox (event_name, event_version, payload)
        VALUES (:name, :ver, {$payloadExpr})
    ";

        $stmt = $this->pdo->prepare($sql);

        if (false === $stmt) {
            throw new \RuntimeException('outbox_prepare_failed');
        }

        $stmt->execute([
            ':name' => $name,
            ':ver' => AddressOutboxEventContract::eventVersion($name),
            ':payload' => $payloadJson,
        ]);
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
    private function boolFilter(array $filters, string $key): ?bool
    {
        $value = $filters[$key] ?? null;
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
     * @return list<array{
     *   normalizationVersion:string,
     *   validationStatus:string,
     *   total:int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   evidenceMissing:int,
     *   dueForRevalidation:int,
     *   uncertainValidation:int,
     *   staleNormalization:int
     * }>
     */
    public function summarizeNormalizationPortfolio(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, array $filters = []): array
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driverAttr) ? $driverAttr : '';
        $params = $this->tenantParams($ownerId, $vendorId);
        $where = ['deleted_at IS NULL', $this->tenantWhereClause($ownerId, $vendorId)];
        if ($countryCode) {
            $where[] = 'country_code = :country_code';
            $params[':country_code'] = $countryCode;
        }
        if ($q) {
            $op = 'pgsql' === $driver ? 'ILIKE' : 'LIKE';
            $where[] = "lower(line1 || ' ' || city || ' ' || coalesce(postal_code,'')) {$op} lower(:q)";
            $params[':q'] = '%'.$q.'%';
        }

        $sourceType = AddressRecordPolicy::normalizeSourceType($this->stringFilter($filters, 'sourceType'));
        if (null !== $sourceType) {
            $where[] = 'source_type = :source_type';
            $params[':source_type'] = $sourceType;
        }

        $sourceSystem = $this->stringFilter($filters, 'sourceSystem');
        if (null !== $sourceSystem) {
            $where[] = 'source_system = :source_system';
            $params[':source_system'] = $sourceSystem;
        }

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

        $hasEvidence = $this->boolFilter($filters, 'hasEvidence');
        if (true === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(true);
        } elseif (false === $hasEvidence) {
            $where[] = $this->evidencePresenceClause(false);
        }

        $expectedNormalizationVersion = $this->stringFilter($filters, 'expectedNormalizationVersion');
        $revalidationDueBefore = $this->stringFilter($filters, 'revalidationDueBefore');
        $params[':summary_due_before'] = $revalidationDueBefore ?? $this->currentTimestampLiteral();
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
            .' ORDER BY total DESC, normalization_version ASC, validation_status ASC';

        $stmt = $this->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        if (null === $expectedNormalizationVersion) {
            $stmt->bindValue(':expected_normalization_version', null, \PDO::PARAM_NULL);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $row): array => [
            'normalizationVersion' => (string) ($row['normalization_version'] ?? ''),
            'validationStatus' => (string) ($row['validation_status'] ?? 'unknown'),
            'total' => (int) ($row['total'] ?? 0),
            'canonical' => (int) ($row['canonical_count'] ?? 0),
            'duplicate' => (int) ($row['duplicate_count'] ?? 0),
            'superseded' => (int) ($row['superseded_count'] ?? 0),
            'alias' => (int) ($row['alias_count'] ?? 0),
            'conflict' => (int) ($row['conflict_count'] ?? 0),
            'evidenceBacked' => (int) ($row['evidence_backed_count'] ?? 0),
            'evidenceMissing' => (int) ($row['evidence_missing_count'] ?? 0),
            'dueForRevalidation' => (int) ($row['due_for_revalidation_count'] ?? 0),
            'uncertainValidation' => (int) ($row['uncertain_validation_count'] ?? 0),
            'staleNormalization' => (int) ($row['stale_normalization_count'] ?? 0),
        ], $rows);
    }
}
