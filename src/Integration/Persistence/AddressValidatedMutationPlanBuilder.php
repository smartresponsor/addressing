<?php

declare(strict_types=1);

namespace App\Integration\Persistence;

use App\Contract\Message\AddressRecordPolicy;
use App\Contract\Message\AddressValidated;
use App\Contract\Message\AddressValidationVerdict;
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
        $updates = [];
        $governance = $this->governanceContext($addressValidated, $id);
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

        $this->addOptionalAssignment($updates, $params, 'line1_norm = :line1_norm', ':line1_norm', $addressValidated->line1Norm);
        $this->addOptionalAssignment($updates, $params, 'city_norm = :city_norm', ':city_norm', $addressValidated->cityNorm);
        $this->addOptionalAssignment($updates, $params, 'region_norm = :region_norm', ':region_norm', $addressValidated->regionNorm);
        $this->addOptionalAssignment($updates, $params, 'postal_code_norm = :postal_code_norm', ':postal_code_norm', $addressValidated->postalCodeNorm);
        $this->addOptionalAssignment($updates, $params, 'latitude = :latitude', ':latitude', $addressValidated->latitude);
        $this->addOptionalAssignment($updates, $params, 'longitude = :longitude', ':longitude', $addressValidated->longitude);
        $this->addOptionalAssignment($updates, $params, 'geohash = :geohash', ':geohash', $addressValidated->geohash);

        $rawSha256 = null;
        if (null !== $addressValidated->raw) {
            $updates[] = $this->jsonAssignment('validation_raw', ':validation_raw');
            $rawPayload = $this->encodePayload($addressValidated->raw);
            $params[':validation_raw'] = $rawPayload;
            $rawSha256 = hash('sha256', $rawPayload);
        }

        $this->appendValidationVerdictAssignments($updates, $params, $addressValidated->addressValidationVerdict);

        $this->addOptionalAssignment($updates, $params, 'source_system = :source_system', ':source_system', $addressValidated->sourceSystem);
        $this->addOptionalAssignment(
            $updates,
            $params,
            'source_type = :source_type',
            ':source_type',
            null !== $addressValidated->sourceType ? AddressRecordPolicy::normalizeSourceType($addressValidated->sourceType) : null,
        );
        $this->addOptionalAssignment($updates, $params, 'source_reference = :source_reference', ':source_reference', $addressValidated->sourceReference);
        $this->addOptionalAssignment(
            $updates,
            $params,
            'normalization_version = :normalization_version',
            ':normalization_version',
            $addressValidated->normalizationVersion,
        );

        if (null !== $addressValidated->rawInput) {
            $updates[] = $this->jsonAssignment('raw_input_snapshot', ':raw_input_snapshot');
            $params[':raw_input_snapshot'] = $this->encodePayload($addressValidated->rawInput);
        }
        if (null !== $normalizedSnapshot) {
            $updates[] = $this->jsonAssignment('normalized_snapshot', ':normalized_snapshot');
            $params[':normalized_snapshot'] = $this->encodePayload($normalizedSnapshot);
        }
        $this->addOptionalAssignment($updates, $params, 'provider_digest = :provider_digest', ':provider_digest', $providerDigest);

        $lastValidation = $this->lastValidationContext($addressValidated);
        $this->addOptionalAssignment(
            $updates,
            $params,
            'revalidation_due_at = :revalidation_due_at',
            ':revalidation_due_at',
            $lastValidation['revalidationDueAt'],
        );
        $this->addOptionalAssignment(
            $updates,
            $params,
            'revalidation_policy = :revalidation_policy',
            ':revalidation_policy',
            $lastValidation['revalidationPolicy'],
        );
        $this->addOptionalAssignment(
            $updates,
            $params,
            'last_validation_provider = :last_validation_provider',
            ':last_validation_provider',
            $lastValidation['lastValidationProvider'],
        );

        $updates[] = 'last_validation_status = :last_validation_status';
        $params[':last_validation_status'] = $lastValidation['lastValidationStatus'];
        $this->addOptionalAssignment(
            $updates,
            $params,
            'last_validation_score = :last_validation_score',
            ':last_validation_score',
            $lastValidation['lastValidationScore'],
        );

        $this->addRequiredAssignment($updates, $params, 'governance_status = :governance_status', ':governance_status', $governance['governanceStatus']);
        $this->addRequiredAssignment($updates, $params, 'duplicate_of_id = :duplicate_of_id', ':duplicate_of_id', $governance['duplicateOfId']);
        $this->addRequiredAssignment($updates, $params, 'superseded_by_id = :superseded_by_id', ':superseded_by_id', $governance['supersededById']);
        $this->addRequiredAssignment($updates, $params, 'alias_of_id = :alias_of_id', ':alias_of_id', $governance['aliasOfId']);
        $this->addRequiredAssignment($updates, $params, 'conflict_with_id = :conflict_with_id', ':conflict_with_id', $governance['conflictWithId']);

        $updates[] = 'validation_provider = :validation_provider';
        $updates[] = 'validation_status = :validation_status';
        $updates[] = 'validated_at = :validated_at';
        $updates[] = 'dedupe_key = :dedupe_key';
        $updates[] = 'validation_fingerprint = :validation_fingerprint';
        $updates[] = 'updated_at = :updated_at';

        return new AddressValidatedMutationPlan(
            $updates,
            $params,
            $governance['governanceStatus'],
            $governance['duplicateOfId'],
            $governance['supersededById'],
            $governance['aliasOfId'],
            $governance['conflictWithId'],
            $normalizedSnapshot,
            $providerDigest,
            $lastValidation['lastValidationStatus'],
            $lastValidation['lastValidationScore'],
            $lastValidation['revalidationDueAt'],
            $lastValidation['revalidationPolicy'],
            $rawSha256,
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
            throw new \RuntimeException('payload_encode_failed');
        }

        return $json;
    }

    /**
     * Detects whether the current connection uses PostgreSQL.
     */
    private function isPgsql(): bool
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return is_string($driver) && 'pgsql' === $driver;
    }

    /**
     * @param list<string>         $updates
     * @param array<string, mixed> $params
     */
    private function addOptionalAssignment(array &$updates, array &$params, string $assignment, string $placeholder, mixed $value): void
    {
        if (null === $value) {
            return;
        }

        $updates[] = $assignment;
        $params[$placeholder] = $value;
    }

    /**
     * @param list<string>         $updates
     * @param array<string, mixed> $params
     */
    private function addRequiredAssignment(array &$updates, array &$params, string $assignment, string $placeholder, mixed $value): void
    {
        $updates[] = $assignment;
        $params[$placeholder] = $value;
    }

    /**
     * @param list<string>         $updates
     * @param array<string, mixed> $params
     */
    private function appendValidationVerdictAssignments(array &$updates, array &$params, ?AddressValidationVerdict $addressValidationVerdict): void
    {
        if (!$addressValidationVerdict instanceof AddressValidationVerdict) {
            return;
        }

        $updates[] = $this->jsonAssignment('validation_verdict', ':validation_verdict');
        $params[':validation_verdict'] = $this->encodePayload($addressValidationVerdict->jsonSerialize());
        $this->addOptionalAssignment(
            $updates,
            $params,
            'validation_deliverable = :validation_deliverable',
            ':validation_deliverable',
            null !== $addressValidationVerdict->deliverable ? ($addressValidationVerdict->deliverable ? 1 : 0) : null,
        );
        $this->addOptionalAssignment(
            $updates,
            $params,
            'validation_granularity = :validation_granularity',
            ':validation_granularity',
            $addressValidationVerdict->granularity,
        );
        $this->addOptionalAssignment(
            $updates,
            $params,
            'validation_quality = :validation_quality',
            ':validation_quality',
            $addressValidationVerdict->quality,
        );
    }

    /**
     * @return array{
     *     governanceStatus: string,
     *     duplicateOfId: ?string,
     *     supersededById: ?string,
     *     aliasOfId: ?string,
     *     conflictWithId: ?string
     * }
     */
    private function governanceContext(AddressValidated $addressValidated, string $id): array
    {
        return [
            'governanceStatus' => AddressRecordPolicy::normalizeGovernanceStatus($addressValidated->governanceStatus),
            'duplicateOfId' => $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($addressValidated->duplicateOfId, $id),
            'supersededById' => $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($addressValidated->supersededById, $id),
            'aliasOfId' => $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($addressValidated->aliasOfId, $id),
            'conflictWithId' => $this->addressValidatedPayloadFactory->sanitizeGovernanceLink($addressValidated->conflictWithId, $id),
        ];
    }

    /**
     * @return array{
     *     lastValidationProvider: ?string,
     *     lastValidationStatus: string,
     *     lastValidationScore: ?int,
     *     revalidationDueAt: ?string,
     *     revalidationPolicy: ?string
     * }
     */
    private function lastValidationContext(AddressValidated $addressValidated): array
    {
        $lastValidationProvider = $addressValidated->lastValidationProvider ?? $addressValidated->validationProvider;
        $lastValidationStatus = $addressValidated->lastValidationStatus ?? 'validated';
        $lastValidationScore = is_int($addressValidated->lastValidationScore)
            ? $addressValidated->lastValidationScore
            : $addressValidated->addressValidationVerdict?->quality;
        $revalidationDueAt = $addressValidated->revalidationDueAt instanceof \DateTimeImmutable
            ? $addressValidated->revalidationDueAt->format('Y-m-d H:i:sP')
            : null;
        $revalidationPolicy = null !== $addressValidated->revalidationPolicy
            ? AddressRecordPolicy::normalizeRevalidationPolicy($addressValidated->revalidationPolicy)
            : null;

        return [
            'lastValidationProvider' => $lastValidationProvider,
            'lastValidationStatus' => $lastValidationStatus,
            'lastValidationScore' => $lastValidationScore,
            'revalidationDueAt' => $revalidationDueAt,
            'revalidationPolicy' => $revalidationPolicy,
        ];
    }
}
