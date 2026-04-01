<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Application;

use App\Contract\Message\AddressOutboxEventContract;
use App\Contract\Message\AddressRecordPolicy;
use App\Contract\Message\AddressValidated;
use App\Integration\Persistence\AddressTenantScopeSqlHelper;
use App\Integration\Persistence\AddressValidatedMutationPlanBuilder;
use App\ServiceInterface\Application\AddressValidatedApplierServiceInterface;

final readonly class AddressValidatedApplierService implements AddressValidatedApplierServiceInterface
{
    public function __construct(
        private \PDO $pdo,
        private AddressTenantScopeSqlHelper $addressTenantScopeSqlHelper,
        private AddressValidatedPayloadFactory $addressValidatedPayloadFactory,
        private AddressValidatedMutationPlanBuilder $addressValidatedMutationPlanBuilder,
    ) {
    }

    #[\Override]
    public function apply(string $id, AddressValidated $addressValidated, ?string $ownerId = null, ?string $vendorId = null): void
    {
        $fingerprint = $addressValidated->fingerprint();
        $now = new \DateTimeImmutable('now');
        $validatedAt = $addressValidated->validatedAt ?? $now;
        $scopeParams = $this->addressTenantScopeSqlHelper->params($ownerId, $vendorId);
        $scopeWhere = $this->addressTenantScopeSqlHelper->whereClause($ownerId, $vendorId);
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

            $plan = $this->addressValidatedMutationPlanBuilder->build($id, $addressValidated, $fingerprint, $now, $validatedAt);

            $sql = 'UPDATE address_entity SET '.$plan->setClause().' WHERE id = :id AND '.$scopeWhere;
            $stmt = $this->prepare($sql);
            $ok = $stmt->execute(array_merge($plan->params, $scopeParams));

            if (!$ok) {
                $this->pdo->rollBack();
                throw new \RuntimeException('apply_failed');
            }
            if ($stmt->rowCount() < 1) {
                $this->pdo->rollBack();
                throw new \RuntimeException('not_found');
            }

            $evidenceSnapshotId = $this->appendEvidenceSnapshot(
                $id,
                $ownerId,
                $vendorId,
                $addressValidated,
                $plan->lastValidationStatus,
                $plan->lastValidationScore,
                $plan->normalizedSnapshot,
                $plan->providerDigest,
            );

            $this->appendOutbox(
                $this->addressValidatedPayloadFactory->outboxPayload(
                    $id,
                    $ownerId,
                    $vendorId,
                    $fingerprint,
                    $addressValidated,
                    $validatedAt,
                    $plan->rawSha256,
                    $plan->governanceStatus,
                    $plan->duplicateOfId,
                    $plan->supersededById,
                    $plan->aliasOfId,
                    $plan->conflictWithId,
                    $plan->revalidationDueAt,
                    $plan->revalidationPolicy,
                    $plan->lastValidationStatus,
                    $plan->lastValidationScore,
                    $evidenceSnapshotId,
                    $plan->providerDigest,
                )
            );

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
