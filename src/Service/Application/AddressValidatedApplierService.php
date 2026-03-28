<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Application;

use App\Contract\Message\AddressOutboxEventContract;
use App\Contract\Message\AddressRecordPolicy;
use App\Contract\Message\AddressValidated;
use App\ServiceInterface\Application\AddressValidatedApplierServiceInterface;

final readonly class AddressValidatedApplierService implements AddressValidatedApplierServiceInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    #[\Override]
    public function apply(string $id, AddressValidated $addressValidated, ?string $ownerId = null, ?string $vendorId = null): void
    {
        $fingerprint = $addressValidated->fingerprint();
        $now = new \DateTimeImmutable('now');
        $validatedAt = $addressValidated->validatedAt ?? $now;
        $scopeParams = $this->tenantParams($ownerId, $vendorId);
        $scopeWhere = $this->tenantWhereClause($ownerId, $vendorId);
        $lockClause = $this->isPgsql() ? ' FOR UPDATE' : '';

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->prepare('SELECT validation_fingerprint FROM address_entity WHERE id = :id AND '.$scopeWhere.$lockClause);
            $stmt->execute(array_merge([':id' => $id], $scopeParams));
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                $this->pdo->rollBack();
                throw new \RuntimeException('not_found');
            }

            /** @var array<string, mixed> $row */
            $prev = $row['validation_fingerprint'] ?? null;
            if (is_string($prev) && '' !== $prev && $prev === $fingerprint) {
                $this->pdo->commit();

                return;
            }

            $fields = [];

            $governanceStatus = AddressRecordPolicy::normalizeGovernanceStatus($addressValidated->governanceStatus);
            $duplicateOfId = $this->sanitizeGovernanceLink($addressValidated->duplicateOfId, $id);
            $supersededById = $this->sanitizeGovernanceLink($addressValidated->supersededById, $id);
            $aliasOfId = $this->sanitizeGovernanceLink($addressValidated->aliasOfId, $id);
            $conflictWithId = $this->sanitizeGovernanceLink($addressValidated->conflictWithId, $id);
            $params = array_merge([
                ':id' => $id,
                ':updated_at' => $now->format('Y-m-d H:i:sP'),
                ':validation_provider' => $addressValidated->validationProvider,
                ':validation_status' => 'validated',
                ':validated_at' => $validatedAt->format('Y-m-d H:i:sP'),
                ':dedupe_key' => $addressValidated->dedupeKey,
                ':validation_fingerprint' => $fingerprint,
            ], $scopeParams);

            if (null !== $addressValidated->line1Norm) {
                $fields[] = 'line1_norm = :line1_norm';
                $params[':line1_norm'] = $addressValidated->line1Norm;
            }
            if (null !== $addressValidated->cityNorm) {
                $fields[] = 'city_norm = :city_norm';
                $params[':city_norm'] = $addressValidated->cityNorm;
            }
            if (null !== $addressValidated->regionNorm) {
                $fields[] = 'region_norm = :region_norm';
                $params[':region_norm'] = $addressValidated->regionNorm;
            }
            if (null !== $addressValidated->postalCodeNorm) {
                $fields[] = 'postal_code_norm = :postal_code_norm';
                $params[':postal_code_norm'] = $addressValidated->postalCodeNorm;
            }
            if (null !== $addressValidated->latitude) {
                $fields[] = 'latitude = :latitude';
                $params[':latitude'] = $addressValidated->latitude;
            }
            if (null !== $addressValidated->longitude) {
                $fields[] = 'longitude = :longitude';
                $params[':longitude'] = $addressValidated->longitude;
            }
            if (null !== $addressValidated->geohash) {
                $fields[] = 'geohash = :geohash';
                $params[':geohash'] = $addressValidated->geohash;
            }

            if (null !== $addressValidated->raw) {
                $fields[] = $this->jsonAssignment('validation_raw', ':validation_raw');
                $rawJson = $this->encodePayload($addressValidated->raw);
                $params[':validation_raw'] = $rawJson;
                $params[':validation_raw_sha256'] = hash('sha256', $rawJson);
            }
            if ($addressValidated->addressValidationVerdict instanceof \App\Contract\Message\AddressValidationVerdict) {
                $fields[] = $this->jsonAssignment('validation_verdict', ':validation_verdict');
                $params[':validation_verdict'] = $this->encodePayload($addressValidated->addressValidationVerdict->jsonSerialize());

                if (null !== $addressValidated->addressValidationVerdict->deliverable) {
                    $fields[] = 'validation_deliverable = :validation_deliverable';
                    $params[':validation_deliverable'] = $addressValidated->addressValidationVerdict->deliverable ? 1 : 0;
                }
                if (null !== $addressValidated->addressValidationVerdict->granularity) {
                    $fields[] = 'validation_granularity = :validation_granularity';
                    $params[':validation_granularity'] = $addressValidated->addressValidationVerdict->granularity;
                }
                if (null !== $addressValidated->addressValidationVerdict->quality) {
                    $fields[] = 'validation_quality = :validation_quality';
                    $params[':validation_quality'] = $addressValidated->addressValidationVerdict->quality;
                }
            }

            if (null !== $addressValidated->sourceSystem) {
                $fields[] = 'source_system = :source_system';
                $params[':source_system'] = $addressValidated->sourceSystem;
            }
            if (null !== $addressValidated->sourceType) {
                $fields[] = 'source_type = :source_type';
                $params[':source_type'] = AddressRecordPolicy::normalizeSourceType($addressValidated->sourceType);
            }
            if (null !== $addressValidated->sourceReference) {
                $fields[] = 'source_reference = :source_reference';
                $params[':source_reference'] = $addressValidated->sourceReference;
            }
            if (null !== $addressValidated->normalizationVersion) {
                $fields[] = 'normalization_version = :normalization_version';
                $params[':normalization_version'] = $addressValidated->normalizationVersion;
            }
            if (null !== $addressValidated->rawInput) {
                $fields[] = $this->jsonAssignment('raw_input_snapshot', ':raw_input_snapshot');
                $params[':raw_input_snapshot'] = $this->encodePayload($addressValidated->rawInput);
            }
            $normalizedSnapshot = $addressValidated->normalizedSnapshot ?? $this->buildNormalizedSnapshot($addressValidated);
            if (null !== $normalizedSnapshot) {
                $fields[] = $this->jsonAssignment('normalized_snapshot', ':normalized_snapshot');
                $params[':normalized_snapshot'] = $this->encodePayload($normalizedSnapshot);
            }
            $providerDigest = $addressValidated->providerDigest ?? $this->buildProviderDigest($addressValidated);
            if (null !== $providerDigest) {
                $fields[] = 'provider_digest = :provider_digest';
                $params[':provider_digest'] = $providerDigest;
            }

            $lastValidationProvider = $addressValidated->lastValidationProvider ?? $addressValidated->validationProvider;
            $lastValidationStatus = $addressValidated->lastValidationStatus ?? 'validated';
            $lastValidationScore = is_int($addressValidated->lastValidationScore)
                ? $addressValidated->lastValidationScore
                : $addressValidated->addressValidationVerdict?->quality;
            if ($addressValidated->revalidationDueAt instanceof \DateTimeImmutable) {
                $fields[] = 'revalidation_due_at = :revalidation_due_at';
                $params[':revalidation_due_at'] = $addressValidated->revalidationDueAt->format('Y-m-d H:i:sP');
            }
            if (null !== $addressValidated->revalidationPolicy) {
                $fields[] = 'revalidation_policy = :revalidation_policy';
                $params[':revalidation_policy'] = AddressRecordPolicy::normalizeRevalidationPolicy($addressValidated->revalidationPolicy);
            }
            if (null !== $lastValidationProvider) {
                $fields[] = 'last_validation_provider = :last_validation_provider';
                $params[':last_validation_provider'] = $lastValidationProvider;
            }
            $fields[] = 'last_validation_status = :last_validation_status';
            $params[':last_validation_status'] = $lastValidationStatus;
            if (null !== $lastValidationScore) {
                $fields[] = 'last_validation_score = :last_validation_score';
                $params[':last_validation_score'] = $lastValidationScore;
            }

            $fields[] = 'governance_status = :governance_status';
            $params[':governance_status'] = $governanceStatus;
            $fields[] = 'duplicate_of_id = :duplicate_of_id';
            $params[':duplicate_of_id'] = $duplicateOfId;
            $fields[] = 'superseded_by_id = :superseded_by_id';
            $params[':superseded_by_id'] = $supersededById;
            $fields[] = 'alias_of_id = :alias_of_id';
            $params[':alias_of_id'] = $aliasOfId;
            $fields[] = 'conflict_with_id = :conflict_with_id';
            $params[':conflict_with_id'] = $conflictWithId;

            $fields[] = 'validation_provider = :validation_provider';
            $fields[] = 'validation_status = :validation_status';
            $fields[] = 'validated_at = :validated_at';
            $fields[] = 'dedupe_key = :dedupe_key';
            $fields[] = 'validation_fingerprint = :validation_fingerprint';
            $fields[] = 'updated_at = :updated_at';

            $sql = 'UPDATE address_entity SET '.implode(', ', $fields).' WHERE id = :id AND '.$scopeWhere;
            $stmt = $this->prepare($sql);
            $ok = $stmt->execute($params);

            if (!$ok) {
                $this->pdo->rollBack();
                throw new \RuntimeException('apply_failed');
            }
            if ($stmt->rowCount() < 1) {
                $this->pdo->rollBack();
                throw new \RuntimeException('not_found');
            }

            $lastValidationStatusParam = $lastValidationStatus;
            $lastValidationScoreParam = $lastValidationScore;

            $evidenceSnapshotId = $this->appendEvidenceSnapshot($id, $ownerId, $vendorId, $addressValidated, $lastValidationStatusParam, $lastValidationScoreParam);

            $this->appendOutbox([
                'id' => $id,
                'ownerId' => $ownerId,
                'vendorId' => $vendorId,
                'fingerprint' => $fingerprint,
                'provider' => $addressValidated->validationProvider,
                'validatedAt' => $validatedAt->format(DATE_ATOM),
                'deliverable' => $addressValidated->addressValidationVerdict?->deliverable,
                'granularity' => $addressValidated->addressValidationVerdict?->granularity,
                'quality' => $addressValidated->addressValidationVerdict?->quality,
                'rawSha256' => $params[':validation_raw_sha256'] ?? null,
                'sourceType' => $addressValidated->sourceType,
                'providerDigest' => $params[':provider_digest'] ?? $addressValidated->providerDigest,
                'hasEvidence' => isset($params[':raw_input_snapshot']) || isset($params[':normalized_snapshot']) || isset($params[':provider_digest']),
                'governanceStatus' => $governanceStatus,
                'governanceLinkId' => $this->governanceLinkId($governanceStatus, $duplicateOfId, $supersededById, $aliasOfId, $conflictWithId),
                'revalidationDueAt' => $params[':revalidation_due_at'] ?? null,
                'revalidationPolicy' => $params[':revalidation_policy'] ?? null,
                'lastValidationStatus' => $lastValidationStatusParam,
                'lastValidationScore' => $lastValidationScoreParam,
                'evidenceSnapshotId' => $evidenceSnapshotId,
            ]);

            $this->pdo->commit();
        } catch (\RuntimeException $e) {
            $this->rollbackIfActive();
            throw $e;
        } catch (\Throwable) {
            $this->rollbackIfActive();
            throw new \RuntimeException('apply_failed');
        }
    }

    private function appendEvidenceSnapshot(
        string $addressId,
        ?string $ownerId,
        ?string $vendorId,
        AddressValidated $addressValidated,
        string $validationStatus,
        ?int $validationScore,
    ): ?string {
        if (!$this->hasEvidence($addressValidated)) {
            return null;
        }

        $snapshotId = bin2hex(random_bytes(16));
        $createdAt = ($addressValidated->validatedAt ?? new \DateTimeImmutable())->format('Y-m-d H:i:sP');
        $validationIssues = $addressValidated->addressValidationVerdict?->jsonSerialize();

        $pdoStatement = $this->prepare(
            'INSERT INTO address_evidence_snapshot (
                id, address_id, owner_id, vendor_id, source_system, source_type, source_reference, validated_by, validated_at,
                normalization_version, raw_input_snapshot, normalized_snapshot, validation_status, validation_score, validation_issues, provider_digest, created_at
            ) VALUES (
                :id, :address_id, :owner_id, :vendor_id, :source_system, :source_type, :source_reference, :validated_by, :validated_at,
                :normalization_version, :raw_input_snapshot, :normalized_snapshot, :validation_status, :validation_score, :validation_issues, :provider_digest, :created_at
            )'
        );

        $pdoStatement->execute([
            ':id' => $snapshotId,
            ':address_id' => $addressId,
            ':owner_id' => $ownerId,
            ':vendor_id' => $vendorId,
            ':source_system' => $addressValidated->sourceSystem,
            ':source_type' => AddressRecordPolicy::normalizeSourceType($addressValidated->sourceType),
            ':source_reference' => $addressValidated->sourceReference,
            ':validated_by' => $addressValidated->validationProvider ?? $addressValidated->lastValidationProvider,
            ':validated_at' => $addressValidated->validatedAt?->format('Y-m-d H:i:sP'),
            ':normalization_version' => $addressValidated->normalizationVersion,
            ':raw_input_snapshot' => $this->encodePayloadNullable($addressValidated->rawInput),
            ':normalized_snapshot' => $this->encodePayloadNullable($addressValidated->normalizedSnapshot ?? $this->buildNormalizedSnapshot($addressValidated)),
            ':validation_status' => AddressRecordPolicy::normalizeValidationStatus($validationStatus),
            ':validation_score' => $validationScore,
            ':validation_issues' => $this->encodePayloadNullable($validationIssues),
            ':provider_digest' => $addressValidated->providerDigest ?? $this->buildProviderDigest($addressValidated),
            ':created_at' => $createdAt,
        ]);

        return $snapshotId;
    }

    private function hasEvidence(AddressValidated $addressValidated): bool
    {
        return null !== $addressValidated->rawInput
            || null !== $addressValidated->normalizedSnapshot
            || null !== $addressValidated->providerDigest
            || null !== $addressValidated->raw
            || $addressValidated->addressValidationVerdict instanceof \App\Contract\Message\AddressValidationVerdict;
    }

    /** @param array<string, mixed> $payload */
    private function appendOutbox(array $payload): void
    {
        $eventName = 'AddressValidatedApplied';
        $payloadJson = $this->encodePayload(AddressOutboxEventContract::decoratePayload($eventName, $payload));
        $payloadExpr = $this->isPgsql() ? ':payload::jsonb' : ':payload';

        $pdoStatement = $this->prepare(
            "INSERT INTO address_outbox (event_name, event_version, payload)
         VALUES (:name, :ver, {$payloadExpr})"
        );

        $pdoStatement->execute([
            ':name' => $eventName,
            ':ver' => AddressOutboxEventContract::eventVersion($eventName),
            ':payload' => $payloadJson,
        ]);
    }

    /** @param array<string, mixed>|null $payload */
    private function encodePayloadNullable(?array $payload): ?string
    {
        if (null === $payload) {
            return null;
        }

        return $this->encodePayload($payload);
    }

    /** @param array<string, mixed> $payload */
    private function encodePayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            throw new \RuntimeException('payload_encode_failed');
        }

        return $json;
    }

    private function jsonAssignment(string $field, string $placeholder): string
    {
        if ($this->isPgsql()) {
            return $field.' = '.$placeholder.'::jsonb';
        }

        return $field.' = '.$placeholder;
    }

    private function sanitizeGovernanceLink(?string $linkId, string $currentId): ?string
    {
        $linkId = is_string($linkId) ? trim($linkId) : '';
        if ('' === $linkId || $linkId === $currentId) {
            return null;
        }

        return $linkId;
    }

    private function governanceLinkId(
        string $governanceStatus,
        ?string $duplicateOfId,
        ?string $supersededById,
        ?string $aliasOfId,
        ?string $conflictWithId,
    ): ?string {
        return match ($governanceStatus) {
            'duplicate' => $duplicateOfId,
            'superseded' => $supersededById,
            'alias' => $aliasOfId,
            'conflict' => $conflictWithId,
            default => null,
        };
    }

    /** @return array<string, mixed>|null */
    private function buildNormalizedSnapshot(AddressValidated $addressValidated): ?array
    {
        $snapshot = array_filter([
            'line1Norm' => $addressValidated->line1Norm,
            'cityNorm' => $addressValidated->cityNorm,
            'regionNorm' => $addressValidated->regionNorm,
            'postalCodeNorm' => $addressValidated->postalCodeNorm,
            'latitude' => $addressValidated->latitude,
            'longitude' => $addressValidated->longitude,
            'geohash' => $addressValidated->geohash,
        ], static fn (mixed $value): bool => null !== $value);

        if ([] === $snapshot) {
            return null;
        }

        /* @var array<string, mixed> $snapshot */
        return $snapshot;
    }

    private function buildProviderDigest(AddressValidated $addressValidated): ?string
    {
        $payload = array_filter([
            'provider' => $addressValidated->validationProvider,
            'validatedAt' => $addressValidated->validatedAt?->format(DATE_ATOM),
            'raw' => $addressValidated->raw,
            'verdict' => $addressValidated->addressValidationVerdict?->jsonSerialize(),
            'normalizedSnapshot' => $addressValidated->normalizedSnapshot,
        ], static fn (mixed $value): bool => null !== $value);

        if ([] === $payload) {
            return null;
        }

        return hash('sha256', $this->encodePayload($payload));
    }

    private function tenantWhereClause(?string $ownerId, ?string $vendorId): string
    {
        if (null !== $ownerId && null !== $vendorId) {
            return '(owner_id = :owner_id AND vendor_id = :vendor_id)';
        }
        if (null !== $ownerId) {
            return '(owner_id = :owner_id)';
        }
        if (null !== $vendorId) {
            return '(vendor_id = :vendor_id)';
        }

        return '1 = 1';
    }

    /** @return array<string, string> */
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

    private function isPgsql(): bool
    {
        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return is_string($driverAttr) && 'pgsql' === $driverAttr;
    }

    private function rollbackIfActive(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function prepare(string $sql): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        if (false === $stmt) {
            throw new \RuntimeException('prepare_failed');
        }

        return $stmt;
    }
}
