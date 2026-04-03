<?php

declare(strict_types=1);

namespace App\Integration\Persistence;

use App\Contract\Message\AddressRecordPolicy;
use App\Contract\Message\AddressValidated;
use App\Service\Application\AddressValidatedPayloadFactory;

final readonly class AddressEvidenceSnapshotWriter
{
    public function __construct(
        private \PDO $pdo,
        private AddressValidatedPayloadFactory $addressValidatedPayloadFactory,
    ) {
    }

    /** @param array<string, mixed>|null $normalizedSnapshot */
    public function write(
        string $addressId,
        ?string $ownerId,
        ?string $vendorId,
        AddressValidated $addressValidated,
        string $validationStatus,
        ?int $validationScore,
        ?array $normalizedSnapshot,
        ?string $providerDigest,
    ): ?string {
        if (!$this->addressValidatedPayloadFactory->hasEvidence($addressValidated)) {
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
            ':normalized_snapshot' => $this->encodePayloadNullable($normalizedSnapshot),
            ':validation_status' => AddressRecordPolicy::normalizeValidationStatus($validationStatus),
            ':validation_score' => $validationScore,
            ':validation_issues' => $this->encodePayloadNullable($validationIssues),
            ':provider_digest' => $providerDigest,
            ':created_at' => $createdAt,
        ]);

        return $snapshotId;
    }

    /** @param array<string, mixed>|null $payload */
    private function encodePayloadNullable(?array $payload): ?string
    {
        if (null === $payload) {
            return null;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            throw new \RuntimeException('payload_encode_failed');
        }

        return $json;
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
