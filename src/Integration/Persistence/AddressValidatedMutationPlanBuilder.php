<?php

declare(strict_types=1);

namespace App\Integration\Persistence;

use App\Contract\Message\AddressRecordPolicy;
use App\Contract\Message\AddressValidated;
use App\Service\Application\AddressValidatedPayloadFactory;

final readonly class AddressValidatedMutationPlanBuilder
{
    public function __construct(
        private \PDO $pdo,
        private AddressValidatedPayloadFactory $address_validatedPayloadFactory,
    ) {
    }

    public function build(
        string $id,
        AddressValidated $address_validated,
        string $fingerprint,
        \DateTimeImmutable $now,
        \DateTimeImmutable $validated_at,
    ): AddressValidatedMutationPlan {
        $update_assignments = [];

        $governance_status = AddressRecordPolicy::normalizeGovernanceStatus($address_validated->governanceStatus);
        $duplicate_of_id = $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($address_validated->duplicateOfId, $id);
        $superseded_by_id = $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($address_validated->supersededById, $id);
        $alias_of_id = $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($address_validated->aliasOfId, $id);
        $conflict_with_id = $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($address_validated->conflictWithId, $id);
        $normalized_snapshot = $this->addressValidatedPayloadFactory->normalizedSnapshot($address_validated);
        $provider_digest = $this->addressValidatedPayloadFactory->providerDigest($address_validated);

        $params = [
            ':id' => $id,
            ':updated_at' => $now->format('Y-m-d H:i:sP'),
            ':validation_provider' => $address_validated->validationProvider,
            ':validation_status' => 'validated',
            ':validated_at' => $validated_at->format('Y-m-d H:i:sP'),
            ':dedupe_key' => $address_validated->dedupeKey,
            ':validation_fingerprint' => $fingerprint,
        ];

        if (null !== $address_validated->line1Norm) {
            $update_assignments[] = 'line1_norm = :line1_norm';
            $params[':line1_norm'] = $address_validated->line1Norm;
        }
        if (null !== $address_validated->cityNorm) {
            $update_assignments[] = 'city_norm = :city_norm';
            $params[':city_norm'] = $address_validated->cityNorm;
        }
        if (null !== $address_validated->regionNorm) {
            $update_assignments[] = 'region_norm = :region_norm';
            $params[':region_norm'] = $address_validated->regionNorm;
        }
        if (null !== $address_validated->postalCodeNorm) {
            $update_assignments[] = 'postal_code_norm = :postal_code_norm';
            $params[':postal_code_norm'] = $address_validated->postalCodeNorm;
        }
        if (null !== $address_validated->latitude) {
            $update_assignments[] = 'latitude = :latitude';
            $params[':latitude'] = $address_validated->latitude;
        }
        if (null !== $address_validated->longitude) {
            $update_assignments[] = 'longitude = :longitude';
            $params[':longitude'] = $address_validated->longitude;
        }
        if (null !== $address_validated->geohash) {
            $update_assignments[] = 'geohash = :geohash';
            $params[':geohash'] = $address_validated->geohash;
        }

        $raw_sha256 = null;
        if (null !== $address_validated->raw) {
            $update_assignments[] = $this->jsonAssignment('validation_raw', ':validation_raw');
            $raw_json = $this->encodePayload($address_validated->raw);
            $params[':validation_raw'] = $raw_json;
            $raw_sha256 = hash('sha256', $raw_json);
        }
        if ($address_validated->addressValidationVerdict instanceof \App\Contract\Message\AddressValidationVerdict) {
            $update_assignments[] = $this->jsonAssignment('validation_verdict', ':validation_verdict');
            $params[':validation_verdict'] = $this->encodePayload($address_validated->addressValidationVerdict->jsonSerialize());

            if (null !== $address_validated->addressValidationVerdict->deliverable) {
                $update_assignments[] = 'validation_deliverable = :validation_deliverable';
                $params[':validation_deliverable'] = $address_validated->addressValidationVerdict->deliverable ? 1 : 0;
            }
            if (null !== $address_validated->addressValidationVerdict->granularity) {
                $update_assignments[] = 'validation_granularity = :validation_granularity';
                $params[':validation_granularity'] = $address_validated->addressValidationVerdict->granularity;
            }
            if (null !== $address_validated->addressValidationVerdict->quality) {
                $update_assignments[] = 'validation_quality = :validation_quality';
                $params[':validation_quality'] = $address_validated->addressValidationVerdict->quality;
            }
        }

        if (null !== $address_validated->sourceSystem) {
            $update_assignments[] = 'source_system = :source_system';
            $params[':source_system'] = $address_validated->sourceSystem;
        }
        if (null !== $address_validated->sourceType) {
            $update_assignments[] = 'source_type = :source_type';
            $params[':source_type'] = AddressRecordPolicy::normalizeSourceType($address_validated->sourceType);
        }
        if (null !== $address_validated->sourceReference) {
            $update_assignments[] = 'source_reference = :source_reference';
            $params[':source_reference'] = $address_validated->sourceReference;
        }
        if (null !== $address_validated->normalizationVersion) {
            $update_assignments[] = 'normalization_version = :normalization_version';
            $params[':normalization_version'] = $address_validated->normalizationVersion;
        }
        if (null !== $address_validated->rawInput) {
            $update_assignments[] = $this->jsonAssignment('raw_input_snapshot', ':raw_input_snapshot');
            $params[':raw_input_snapshot'] = $this->encodePayload($address_validated->rawInput);
        }
        if (null !== $normalized_snapshot) {
            $update_assignments[] = $this->jsonAssignment('normalized_snapshot', ':normalized_snapshot');
            $params[':normalized_snapshot'] = $this->encodePayload($normalized_snapshot);
        }
        if (null !== $provider_digest) {
            $update_assignments[] = 'provider_digest = :provider_digest';
            $params[':provider_digest'] = $provider_digest;
        }

        $lastValidationProvider = $address_validated->lastValidationProvider ?? $address_validated->validationProvider;
        $lastValidationStatus = $address_validated->lastValidationStatus ?? 'validated';
        $lastValidationScore = is_int($address_validated->lastValidationScore)
            ? $address_validated->lastValidationScore
            : $address_validated->addressValidationVerdict?->quality;
        $revalidationDueAt = null;
        if ($address_validated->revalidationDueAt instanceof \DateTimeImmutable) {
            $revalidationDueAt = $address_validated->revalidationDueAt->format('Y-m-d H:i:sP');
            $update_assignments[] = 'revalidation_due_at = :revalidation_due_at';
            $params[':revalidation_due_at'] = $revalidationDueAt;
        }
        $revalidation_policy = null;
        if (null !== $address_validated->revalidationPolicy) {
            $revalidation_policy = AddressRecordPolicy::normalizeRevalidationPolicy($address_validated->revalidationPolicy);
            $update_assignments[] = 'revalidation_policy = :revalidation_policy';
            $params[':revalidation_policy'] = $revalidation_policy;
        }
        if (null !== $lastValidationProvider) {
            $update_assignments[] = 'last_validation_provider = :last_validation_provider';
            $params[':last_validation_provider'] = $lastValidationProvider;
        }
        $update_assignments[] = 'last_validation_status = :last_validation_status';
        $params[':last_validation_status'] = $lastValidationStatus;
        if (null !== $lastValidationScore) {
            $update_assignments[] = 'last_validation_score = :last_validation_score';
            $params[':last_validation_score'] = $lastValidationScore;
        }

        $update_assignments[] = 'governance_status = :governance_status';
        $params[':governance_status'] = $governance_status;
        $update_assignments[] = 'duplicate_of_id = :duplicate_of_id';
        $params[':duplicate_of_id'] = $duplicate_of_id;
        $update_assignments[] = 'superseded_by_id = :superseded_by_id';
        $params[':superseded_by_id'] = $superseded_by_id;
        $update_assignments[] = 'alias_of_id = :alias_of_id';
        $params[':alias_of_id'] = $alias_of_id;
        $update_assignments[] = 'conflict_with_id = :conflict_with_id';
        $params[':conflict_with_id'] = $conflict_with_id;

        $update_assignments[] = 'validation_provider = :validation_provider';
        $update_assignments[] = 'validation_status = :validation_status';
        $update_assignments[] = 'validated_at = :validated_at';
        $update_assignments[] = 'dedupe_key = :dedupe_key';
        $update_assignments[] = 'validation_fingerprint = :validation_fingerprint';
        $update_assignments[] = 'updated_at = :updated_at';

        return new AddressValidatedMutationPlan(
            $update_assignments,
            $params,
            $governance_status,
            $duplicate_of_id,
            $superseded_by_id,
            $alias_of_id,
            $conflict_with_id,
            $normalized_snapshot,
            $provider_digest,
            $lastValidationStatus,
            $lastValidationScore,
            $revalidationDueAt,
            $revalidation_policy,
            $raw_sha256,
        );
    }

    private function jsonAssignment(string $field, string $placeholder): string
    {
        if ($this->isPgsql()) {
            return $field.' = '.$placeholder.'::jsonb';
        }

        return $field.' = '.$placeholder;
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

    private function isPgsql(): bool
    {
        $driver_attr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return is_string($driver_attr) && 'pgsql' === $driver_attr;
    }
}
