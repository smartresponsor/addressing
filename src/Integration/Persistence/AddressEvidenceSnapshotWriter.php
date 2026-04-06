<?php

declare(strict_types=1);

namespace App\Integration\Persistence;

use App\Contract\Message\AddressRecordPolicy;
use App\Contract\Message\AddressValidated;
use App\Service\Application\AddressValidatedPayloadFactory;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use RuntimeException;

/** Persists evidence snapshots derived from address validation messages. */
final readonly class AddressEvidenceSnapshotWriter
{
    public function __construct(
        private PDO $pdo,
        private AddressValidatedPayloadFactory $addressValidatedPayloadFactory,
    ) {
    }

    /** @param array<string, mixed>|null $normalizedSnapshot */
    public function write(
        string $addressId,
        ?string $owner_id,
        ?string $vendor_id,
        AddressValidated $address_validated,
        string $validationStatus,
        ?int $validationScore,
        ?array $normalizedSnapshot,
        ?string $providerDigest,
    ): ?string {
        if (!$this->addressValidatedPayloadFactory->hasEvidence($address_validated)) {
            return null;
        }

        $snapshotId = bin2hex(random_bytes(16));
        $created_at = ($address_validated->validatedAt ?? new DateTimeImmutable())->format('Y-m-d H:i:sP');
        $validation_issues = $address_validated->addressValidationVerdict?->jsonSerialize();

        $pdo_statement = $this->prepare(
            'INSERT INTO address_evidence_snapshot (
                id, address_id, owner_id, vendor_id, source_system, source_type, source_reference, validated_by, validated_at,
                normalization_version, raw_input_snapshot, normalized_snapshot, validation_status, validation_score, validation_issues, provider_digest, created_at
            ) VALUES (
                :id, :address_id, :owner_id, :vendor_id, :source_system, :source_type, :source_reference, :validated_by, :validated_at,
                :normalization_version, :raw_input_snapshot, :normalized_snapshot, :validation_status, :validation_score, :validation_issues, :provider_digest, :created_at
            )'
        );

        $pdo_statement->execute([
            ':id' => $snapshotId,
            ':address_id' => $addressId,
            ':owner_id' => $owner_id,
            ':vendor_id' => $vendor_id,
            ':source_system' => $address_validated->sourceSystem,
            ':source_type' => AddressRecordPolicy::normalizeSourceType($address_validated->sourceType),
            ':source_reference' => $address_validated->sourceReference,
            ':validated_by' => $address_validated->validationProvider ?? $address_validated->lastValidationProvider,
            ':validated_at' => $address_validated->validatedAt?->format('Y-m-d H:i:sP'),
            ':normalization_version' => $address_validated->normalizationVersion,
            ':raw_input_snapshot' => $this->encodePayloadNullable($address_validated->rawInput),
            ':normalized_snapshot' => $this->encodePayloadNullable($normalizedSnapshot),
            ':validation_status' => AddressRecordPolicy::normalizeValidationStatus($validationStatus),
            ':validation_score' => $validationScore,
            ':validation_issues' => $this->encodePayloadNullable($validation_issues),
            ':provider_digest' => $providerDigest,
            ':created_at' => $created_at,
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
            throw new RuntimeException('payload_encode_failed');
        }

        return $json;
    }

    private function prepare(string $sql): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        if (false === $stmt) {
            throw new RuntimeException('prepare_failed');
        }

        return $stmt;
    }
}
