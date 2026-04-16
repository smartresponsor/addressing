<?php

declare(strict_types=1);

namespace App\Integration\Persistence;

use App\Contract\Message\AddressRecordPolicy;
use App\Service\Application\AddressValidatedPayloadFactory;

/** Persists evidence snapshots derived from address validation messages. */
final readonly class AddressEvidenceSnapshotWriter
{
    public function __construct(
        private \PDO $pdo,
        private AddressValidatedPayloadFactory $payloadFactory,
    ) {
    }

    public function write(AddressEvidenceSnapshotContext $context): ?string
    {
        if (!$this->payloadFactory->hasEvidence($context->addressValidated)) {
            return null;
        }

        $snapshotId = $this->newSnapshotId();
        $createdAt = ($context->addressValidated->validatedAt ?? new \DateTimeImmutable())->format('Y-m-d H:i:sP');
        $validationIssues = $context->addressValidated->addressValidationVerdict?->jsonSerialize();

        $statement = $this->prepareEvidenceInsert();

        $statement->execute($this->snapshotParams(
            context: $context,
            snapshotId: $snapshotId,
            createdAt: $createdAt,
            validationIssues: $validationIssues,
        ));

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

    private function newSnapshotId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $throwable) {
            throw new \RuntimeException('address_evidence_snapshot_id_failed', 0, $throwable);
        }
    }

    /**
     * @param array<string, mixed>|null $validationIssues
     *
     * @return array<string, mixed>
     */
    private function snapshotParams(
        AddressEvidenceSnapshotContext $context,
        string $snapshotId,
        string $createdAt,
        ?array $validationIssues,
    ): array {
        return [
            ':id' => $snapshotId,
            ':address_id' => $context->addressId,
            ':owner_id' => $context->ownerId,
            ':vendor_id' => $context->vendorId,
            ':source_system' => $context->addressValidated->sourceSystem,
            ':source_type' => AddressRecordPolicy::normalizeSourceType($context->addressValidated->sourceType),
            ':source_reference' => $context->addressValidated->sourceReference,
            ':validated_by' => $context->addressValidated->validationProvider ?? $context->addressValidated->lastValidationProvider,
            ':validated_at' => $context->addressValidated->validatedAt?->format('Y-m-d H:i:sP'),
            ':normalization_version' => $context->addressValidated->normalizationVersion,
            ':raw_input_snapshot' => $this->encodePayloadNullable($context->addressValidated->rawInput),
            ':normalized_snapshot' => $this->encodePayloadNullable($context->normalizedSnapshot),
            ':validation_status' => AddressRecordPolicy::normalizeValidationStatus($context->validationStatus),
            ':validation_score' => $context->validationScore,
            ':validation_issues' => $this->encodePayloadNullable($validationIssues),
            ':provider_digest' => $context->providerDigest,
            ':created_at' => $createdAt,
        ];
    }

    private function prepareEvidenceInsert(): \PDOStatement
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO address_evidence_snapshot (
                id, address_id, owner_id, vendor_id, source_system, source_type, source_reference, validated_by, validated_at,
                normalization_version, raw_input_snapshot, normalized_snapshot, validation_status, validation_score, validation_issues, provider_digest, created_at
            ) VALUES (
                :id, :address_id, :owner_id, :vendor_id, :source_system, :source_type, :source_reference, :validated_by, :validated_at,
                :normalization_version, :raw_input_snapshot, :normalized_snapshot, :validation_status, :validation_score, :validation_issues, :provider_digest, :created_at
            )'
        );
        if (false === $statement) {
            throw new \RuntimeException('prepare_failed');
        }

        return $statement;
    }
}
