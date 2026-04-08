<?php

declare(strict_types=1);

namespace App\Integration\Persistence;

use App\Contract\Message\AddressRecordPolicy;
use App\Contract\Message\AddressValidated;
use App\Contract\Message\AddressValidationVerdict;
use App\Service\Application\AddressValidatedPayloadFactory;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final readonly class AddressValidatedMutationPlanBuilder
{
    public function __construct(
        private PDO $pdo,
        private AddressValidatedPayloadFactory $addressValidatedPayloadFactory,
    ) {
    }

    public function build(
        string $id,
        AddressValidated $addressValidated,
        string $fingerprint,
        DateTimeImmutable $now,
        DateTimeImmutable $validated_at,
    ): AddressValidatedMutationPlan {
        $updates = [];

        $gov_status = AddressRecordPolicy::normalizeGovernanceStatus($addressValidated->governanceStatus);
        $dup_of_id = $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($addressValidated->duplicateOfId, $id);
        $sup_by_id = $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($addressValidated->supersededById, $id);
        $alias_id = $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($addressValidated->aliasOfId, $id);
        $conflict_id = $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($addressValidated->conflictWithId, $id);
        $norm_snapshot = $this->addressValidatedPayloadFactory->normalizedSnapshot($addressValidated);
        $prov_digest = $this->addressValidatedPayloadFactory->providerDigest($addressValidated);

        $params = [
            ':id' => $id,
            ':updated_at' => $now->format('Y-m-d H:i:sP'),
            ':validation_provider' => $addressValidated->validationProvider,
            ':validation_status' => 'validated',
            ':validated_at' => $validated_at->format('Y-m-d H:i:sP'),
            ':dedupe_key' => $addressValidated->dedupeKey,
            ':validation_fingerprint' => $fingerprint,
        ];

        if (null !== $addressValidated->line1Norm) {
            $updates[] = 'line1_norm = :line1_norm';
            $params[':line1_norm'] = $addressValidated->line1Norm;
        }
        if (null !== $addressValidated->cityNorm) {
            $updates[] = 'city_norm = :city_norm';
            $params[':city_norm'] = $addressValidated->cityNorm;
        }
        if (null !== $addressValidated->regionNorm) {
            $updates[] = 'region_norm = :region_norm';
            $params[':region_norm'] = $addressValidated->regionNorm;
        }
        if (null !== $addressValidated->postalCodeNorm) {
            $updates[] = 'postal_code_norm = :postal_code_norm';
            $params[':postal_code_norm'] = $addressValidated->postalCodeNorm;
        }
        if (null !== $addressValidated->latitude) {
            $updates[] = 'latitude = :latitude';
            $params[':latitude'] = $addressValidated->latitude;
        }
        if (null !== $addressValidated->longitude) {
            $updates[] = 'longitude = :longitude';
            $params[':longitude'] = $addressValidated->longitude;
        }
        if (null !== $addressValidated->geohash) {
            $updates[] = 'geohash = :geohash';
            $params[':geohash'] = $addressValidated->geohash;
        }

        $raw_hash = null;
        if (null !== $addressValidated->raw) {
            $updates[] = $this->jsonAssignment('validation_raw', ':validation_raw');
            $raw_payload = $this->encodePayload($addressValidated->raw);
            $params[':validation_raw'] = $raw_payload;
            $raw_hash = hash('sha256', $raw_payload);
        }
        if ($addressValidated->addressValidationVerdict instanceof AddressValidationVerdict) {
            $updates[] = $this->jsonAssignment('validation_verdict', ':validation_verdict');
            $params[':validation_verdict'] = $this->encodePayload($addressValidated->addressValidationVerdict->jsonSerialize());

            if (null !== $addressValidated->addressValidationVerdict->deliverable) {
                $updates[] = 'validation_deliverable = :validation_deliverable';
                $params[':validation_deliverable'] = $addressValidated->addressValidationVerdict->deliverable ? 1 : 0;
            }
            if (null !== $addressValidated->addressValidationVerdict->granularity) {
                $updates[] = 'validation_granularity = :validation_granularity';
                $params[':validation_granularity'] = $addressValidated->addressValidationVerdict->granularity;
            }
            if (null !== $addressValidated->addressValidationVerdict->quality) {
                $updates[] = 'validation_quality = :validation_quality';
                $params[':validation_quality'] = $addressValidated->addressValidationVerdict->quality;
            }
        }

        if (null !== $addressValidated->sourceSystem) {
            $updates[] = 'source_system = :source_system';
            $params[':source_system'] = $addressValidated->sourceSystem;
        }
        if (null !== $addressValidated->sourceType) {
            $updates[] = 'source_type = :source_type';
            $params[':source_type'] = AddressRecordPolicy::normalizeSourceType($addressValidated->sourceType);
        }
        if (null !== $addressValidated->sourceReference) {
            $updates[] = 'source_reference = :source_reference';
            $params[':source_reference'] = $addressValidated->sourceReference;
        }
        if (null !== $addressValidated->normalizationVersion) {
            $updates[] = 'normalization_version = :normalization_version';
            $params[':normalization_version'] = $addressValidated->normalizationVersion;
        }
        if (null !== $addressValidated->rawInput) {
            $updates[] = $this->jsonAssignment('raw_input_snapshot', ':raw_input_snapshot');
            $params[':raw_input_snapshot'] = $this->encodePayload($addressValidated->rawInput);
        }
        if (null !== $norm_snapshot) {
            $updates[] = $this->jsonAssignment('normalized_snapshot', ':normalized_snapshot');
            $params[':normalized_snapshot'] = $this->encodePayload($norm_snapshot);
        }
        if (null !== $prov_digest) {
            $updates[] = 'provider_digest = :provider_digest';
            $params[':provider_digest'] = $prov_digest;
        }

        $last_provider = $addressValidated->lastValidationProvider ?? $addressValidated->validationProvider;
        $last_status = $addressValidated->lastValidationStatus ?? 'validated';
        $last_score = is_int($addressValidated->lastValidationScore)
            ? $addressValidated->lastValidationScore
            : $addressValidated->addressValidationVerdict?->quality;
        $due_at = null;
        if ($addressValidated->revalidationDueAt instanceof \DateTimeImmutable) {
            $due_at = $addressValidated->revalidationDueAt->format('Y-m-d H:i:sP');
            $updates[] = 'revalidation_due_at = :revalidation_due_at';
            $params[':revalidation_due_at'] = $due_at;
        }
        $reval_policy = null;
        if (null !== $addressValidated->revalidationPolicy) {
            $reval_policy = AddressRecordPolicy::normalizeRevalidationPolicy($addressValidated->revalidationPolicy);
            $updates[] = 'revalidation_policy = :revalidation_policy';
            $params[':revalidation_policy'] = $reval_policy;
        }
        if (null !== $last_provider) {
            $updates[] = 'last_validation_provider = :last_validation_provider';
            $params[':last_validation_provider'] = $last_provider;
        }
        $updates[] = 'last_validation_status = :last_validation_status';
        $params[':last_validation_status'] = $last_status;
        if (null !== $last_score) {
            $updates[] = 'last_validation_score = :last_validation_score';
            $params[':last_validation_score'] = $last_score;
        }

        $updates[] = 'governance_status = :governance_status';
        $params[':governance_status'] = $gov_status;
        $updates[] = 'duplicate_of_id = :duplicate_of_id';
        $params[':duplicate_of_id'] = $dup_of_id;
        $updates[] = 'superseded_by_id = :superseded_by_id';
        $params[':superseded_by_id'] = $sup_by_id;
        $updates[] = 'alias_of_id = :alias_of_id';
        $params[':alias_of_id'] = $alias_id;
        $updates[] = 'conflict_with_id = :conflict_with_id';
        $params[':conflict_with_id'] = $conflict_id;

        $updates[] = 'validation_provider = :validation_provider';
        $updates[] = 'validation_status = :validation_status';
        $updates[] = 'validated_at = :validated_at';
        $updates[] = 'dedupe_key = :dedupe_key';
        $updates[] = 'validation_fingerprint = :validation_fingerprint';
        $updates[] = 'updated_at = :updated_at';

        return new AddressValidatedMutationPlan(
            $updates,
            $params,
            $gov_status,
            $dup_of_id,
            $sup_by_id,
            $alias_id,
            $conflict_id,
            $norm_snapshot,
            $prov_digest,
            $last_status,
            $last_score,
            $due_at,
            $reval_policy,
            $raw_hash,
        );
    }

    /**
     * Creates the assignment fragment for JSON-capable columns.
     */
    private function jsonAssignment(string $field, string $placeholder): string
    {
        if ($this->isPgsql()) {
            return $field.' = '.$placeholder.'::jsonb';
        }

        return $field.' = '.$placeholder;
    }

    /**
     * Encodes a payload for persistence.
     *
     * @param array<string, mixed> $payload
     */
    private function encodePayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            throw new RuntimeException('payload_encode_failed');
        }

        return $json;
    }

    /**
     * Detects whether the current connection uses PostgreSQL.
     */
    private function isPgsql(): bool
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return is_string($driver) && 'pgsql' === $driver;
    }
}
