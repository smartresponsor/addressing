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
        private AddressValidatedPayloadFactory $addressValidatedPayloadFactory,
    ) {
    }

    public function build(
        string $id,
        AddressValidated $addressValidated,
        string $fingerprint,
        \DateTimeImmutable $now,
        \DateTimeImmutable $validatedAt,
    ): AddressValidatedMutationPlan {
        $updateAssignments = [];

        $governanceStatus = AddressRecordPolicy::normalizeGovernanceStatus($addressValidated->governanceStatus);
        $duplicateOfId = $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($addressValidated->duplicateOfId, $id);
        $supersededById = $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($addressValidated->supersededById, $id);
        $aliasOfId = $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($addressValidated->aliasOfId, $id);
        $conflictWithId = $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($addressValidated->conflictWithId, $id);
        $normalizedSnapshot = $this->addressValidatedPayloadFactory->normalizedSnapshot($addressValidated);
        $providerDigest = $this->addressValidatedPayloadFactory->providerDigest($addressValidated);

        $params = [
            ':id' => $id,
            ':updated_at' => $now->format('Y-m-d H:i:sP'),
            ':validation_provider' => $addressValidated->validationProvider,
            ':validation_status' => 'validated',
            ':validated_at' => $validatedAt->format('Y-m-d H:i:sP'),
            ':dedupe_key' => $addressValidated->dedupeKey,
            ':validation_fingerprint' => $fingerprint,
        ];

        if (null !== $addressValidated->line1Norm) {
            $updateAssignments[] = 'line1_norm = :line1_norm';
            $params[':line1_norm'] = $addressValidated->line1Norm;
        }
        if (null !== $addressValidated->cityNorm) {
            $updateAssignments[] = 'city_norm = :city_norm';
            $params[':city_norm'] = $addressValidated->cityNorm;
        }
        if (null !== $addressValidated->regionNorm) {
            $updateAssignments[] = 'region_norm = :region_norm';
            $params[':region_norm'] = $addressValidated->regionNorm;
        }
        if (null !== $addressValidated->postalCodeNorm) {
            $updateAssignments[] = 'postal_code_norm = :postal_code_norm';
            $params[':postal_code_norm'] = $addressValidated->postalCodeNorm;
        }
        if (null !== $addressValidated->latitude) {
            $updateAssignments[] = 'latitude = :latitude';
            $params[':latitude'] = $addressValidated->latitude;
        }
        if (null !== $addressValidated->longitude) {
            $updateAssignments[] = 'longitude = :longitude';
            $params[':longitude'] = $addressValidated->longitude;
        }
        if (null !== $addressValidated->geohash) {
            $updateAssignments[] = 'geohash = :geohash';
            $params[':geohash'] = $addressValidated->geohash;
        }

        $rawSha256 = null;
        if (null !== $addressValidated->raw) {
            $updateAssignments[] = $this->jsonAssignment('validation_raw', ':validation_raw');
            $rawJson = $this->encodePayload($addressValidated->raw);
            $params[':validation_raw'] = $rawJson;
            $rawSha256 = hash('sha256', $rawJson);
        }
        if ($addressValidated->addressValidationVerdict instanceof \App\Contract\Message\AddressValidationVerdict) {
            $updateAssignments[] = $this->jsonAssignment('validation_verdict', ':validation_verdict');
            $params[':validation_verdict'] = $this->encodePayload($addressValidated->addressValidationVerdict->jsonSerialize());

            if (null !== $addressValidated->addressValidationVerdict->deliverable) {
                $updateAssignments[] = 'validation_deliverable = :validation_deliverable';
                $params[':validation_deliverable'] = $addressValidated->addressValidationVerdict->deliverable ? 1 : 0;
            }
            if (null !== $addressValidated->addressValidationVerdict->granularity) {
                $updateAssignments[] = 'validation_granularity = :validation_granularity';
                $params[':validation_granularity'] = $addressValidated->addressValidationVerdict->granularity;
            }
            if (null !== $addressValidated->addressValidationVerdict->quality) {
                $updateAssignments[] = 'validation_quality = :validation_quality';
                $params[':validation_quality'] = $addressValidated->addressValidationVerdict->quality;
            }
        }

        if (null !== $addressValidated->sourceSystem) {
            $updateAssignments[] = 'source_system = :source_system';
            $params[':source_system'] = $addressValidated->sourceSystem;
        }
        if (null !== $addressValidated->sourceType) {
            $updateAssignments[] = 'source_type = :source_type';
            $params[':source_type'] = AddressRecordPolicy::normalizeSourceType($addressValidated->sourceType);
        }
        if (null !== $addressValidated->sourceReference) {
            $updateAssignments[] = 'source_reference = :source_reference';
            $params[':source_reference'] = $addressValidated->sourceReference;
        }
        if (null !== $addressValidated->normalizationVersion) {
            $updateAssignments[] = 'normalization_version = :normalization_version';
            $params[':normalization_version'] = $addressValidated->normalizationVersion;
        }
        if (null !== $addressValidated->rawInput) {
            $updateAssignments[] = $this->jsonAssignment('raw_input_snapshot', ':raw_input_snapshot');
            $params[':raw_input_snapshot'] = $this->encodePayload($addressValidated->rawInput);
        }
        if (null !== $normalizedSnapshot) {
            $updateAssignments[] = $this->jsonAssignment('normalized_snapshot', ':normalized_snapshot');
            $params[':normalized_snapshot'] = $this->encodePayload($normalizedSnapshot);
        }
        if (null !== $providerDigest) {
            $updateAssignments[] = 'provider_digest = :provider_digest';
            $params[':provider_digest'] = $providerDigest;
        }

        $lastValidationProvider = $addressValidated->lastValidationProvider ?? $addressValidated->validationProvider;
        $lastValidationStatus = $addressValidated->lastValidationStatus ?? 'validated';
        $lastValidationScore = is_int($addressValidated->lastValidationScore)
            ? $addressValidated->lastValidationScore
            : $addressValidated->addressValidationVerdict?->quality;
        $revalidationDueAt = null;
        if ($addressValidated->revalidationDueAt instanceof \DateTimeImmutable) {
            $revalidationDueAt = $addressValidated->revalidationDueAt->format('Y-m-d H:i:sP');
            $updateAssignments[] = 'revalidation_due_at = :revalidation_due_at';
            $params[':revalidation_due_at'] = $revalidationDueAt;
        }
        $revalidationPolicy = null;
        if (null !== $addressValidated->revalidationPolicy) {
            $revalidationPolicy = AddressRecordPolicy::normalizeRevalidationPolicy($addressValidated->revalidationPolicy);
            $updateAssignments[] = 'revalidation_policy = :revalidation_policy';
            $params[':revalidation_policy'] = $revalidationPolicy;
        }
        if (null !== $lastValidationProvider) {
            $updateAssignments[] = 'last_validation_provider = :last_validation_provider';
            $params[':last_validation_provider'] = $lastValidationProvider;
        }
        $updateAssignments[] = 'last_validation_status = :last_validation_status';
        $params[':last_validation_status'] = $lastValidationStatus;
        if (null !== $lastValidationScore) {
            $updateAssignments[] = 'last_validation_score = :last_validation_score';
            $params[':last_validation_score'] = $lastValidationScore;
        }

        $updateAssignments[] = 'governance_status = :governance_status';
        $params[':governance_status'] = $governanceStatus;
        $updateAssignments[] = 'duplicate_of_id = :duplicate_of_id';
        $params[':duplicate_of_id'] = $duplicateOfId;
        $updateAssignments[] = 'superseded_by_id = :superseded_by_id';
        $params[':superseded_by_id'] = $supersededById;
        $updateAssignments[] = 'alias_of_id = :alias_of_id';
        $params[':alias_of_id'] = $aliasOfId;
        $updateAssignments[] = 'conflict_with_id = :conflict_with_id';
        $params[':conflict_with_id'] = $conflictWithId;

        $updateAssignments[] = 'validation_provider = :validation_provider';
        $updateAssignments[] = 'validation_status = :validation_status';
        $updateAssignments[] = 'validated_at = :validated_at';
        $updateAssignments[] = 'dedupe_key = :dedupe_key';
        $updateAssignments[] = 'validation_fingerprint = :validation_fingerprint';
        $updateAssignments[] = 'updated_at = :updated_at';

        return new AddressValidatedMutationPlan(
            $updateAssignments,
            $params,
            $governanceStatus,
            $duplicateOfId,
            $supersededById,
            $aliasOfId,
            $conflictWithId,
            $normalizedSnapshot,
            $providerDigest,
            $lastValidationStatus,
            $lastValidationScore,
            $revalidationDueAt,
            $revalidationPolicy,
            $rawSha256,
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
        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return is_string($driverAttr) && 'pgsql' === $driverAttr;
    }
}
