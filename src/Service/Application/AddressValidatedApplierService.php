<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Application;

use App\Contract\Message\AddressValidated;
use App\Integration\Persistence\AddressEvidenceSnapshotContext;
use App\Integration\Persistence\AddressEvidenceSnapshotWriter;
use App\Integration\Persistence\AddressOutboxWriter;
use App\Integration\Persistence\AddressTenantScopeSqlHelper;
use App\Integration\Persistence\AddressValidatedMutationPlan;
use App\Integration\Persistence\AddressValidatedMutationPlanBuilder;
use App\ServiceInterface\Application\AddressValidatedApplierServiceInterface;

final readonly class AddressValidatedApplierService implements AddressValidatedApplierServiceInterface
{
    private AddressTenantScopeSqlHelper $scopeSqlHelper;
    private AddressValidatedPayloadFactory $payloadFactory;
    private AddressValidatedMutationPlanBuilder $planBuilder;
    private AddressEvidenceSnapshotWriter $snapshotWriter;
    private AddressOutboxWriter $outboxWriter;

    public function __construct(
        private \PDO $pdo,
        ?AddressTenantScopeSqlHelper $scopeSqlHelper = null,
        ?AddressValidatedPayloadFactory $payloadFactory = null,
        ?AddressValidatedMutationPlanBuilder $planBuilder = null,
        ?AddressEvidenceSnapshotWriter $snapshotWriter = null,
        ?AddressOutboxWriter $outboxWriter = null,
    ) {
        $this->scopeSqlHelper = $scopeSqlHelper ?? new AddressTenantScopeSqlHelper();
        $this->payloadFactory = $payloadFactory ?? new AddressValidatedPayloadFactory();
        $this->planBuilder = $planBuilder
            ?? new AddressValidatedMutationPlanBuilder($pdo, $this->payloadFactory);
        $this->snapshotWriter = $snapshotWriter
            ?? new AddressEvidenceSnapshotWriter($pdo, $this->payloadFactory);
        $this->outboxWriter = $outboxWriter ?? new AddressOutboxWriter($pdo);
    }

    #[\Override]
    public function apply(string $id, AddressValidated $addressValidated, ?string $ownerId = null, ?string $vendorId = null): void
    {
        $fingerprint = $addressValidated->fingerprint();
        $now = new \DateTimeImmutable('now');
        $validatedAt = $addressValidated->validatedAt ?? $now;
        $scopeParams = $this->scopeSqlHelper->params($ownerId, $vendorId);
        $scopeWhere = $this->scopeSqlHelper->whereClause($ownerId, $vendorId);
        $lockClause = $this->isPgsql() ? ' FOR UPDATE' : '';

        try {
            $this->pdo->beginTransaction();

            $existingFingerprint = $this->loadValidationFingerprint($id, $scopeWhere, $scopeParams, $lockClause);
            if (null !== $existingFingerprint && $existingFingerprint === $fingerprint) {
                $this->pdo->commit();

                return;
            }

            $plan = $this->planBuilder->build(
                $id,
                $addressValidated,
                $fingerprint,
                $now,
                $validatedAt,
            );

            $this->applyMutation($id, $scopeWhere, $scopeParams, $plan);

            $evidenceSnapshotId = $this->snapshotWriter->write(
                AddressEvidenceSnapshotContext::fromMutationPlan(
                    addressId: $id,
                    ownerId: $ownerId,
                    vendorId: $vendorId,
                    addressValidated: $addressValidated,
                    plan: $plan,
                )
            );

            $this->outboxWriter->write(
                $this->payloadFactory->outboxPayload(
                    AddressValidatedOutboxContext::fromMutationPlan(
                        id: $id,
                        ownerId: $ownerId,
                        vendorId: $vendorId,
                        fingerprint: $fingerprint,
                        validatedAt: $validatedAt,
                        evidenceSnapshotId: $evidenceSnapshotId,
                        plan: $plan,
                    ),
                    $addressValidated,
                )
            );

            $this->pdo->commit();
        } catch (\RuntimeException $runtimeException) {
            $this->rollbackIfActive();
            throw $runtimeException;
        } catch (\Throwable $throwable) {
            $this->rollbackIfActive();
            throw new \RuntimeException('apply_failed', previous: $throwable);
        }
    }

    /** @param array<string, mixed> $scopeParams */
    private function loadValidationFingerprint(
        string $id,
        string $scopeWhere,
        array $scopeParams,
        string $lockClause,
    ): ?string {
        $statement = $this->prepare(
            'SELECT validation_fingerprint FROM address_entity WHERE id = :id AND '.$scopeWhere.$lockClause
        );
        $statement->execute(array_merge([':id' => $id], $scopeParams));
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new \RuntimeException('not_found');
        }

        $fingerprint = $row['validation_fingerprint'] ?? null;

        return is_string($fingerprint) && '' !== $fingerprint ? $fingerprint : null;
    }

    /** @param array<string, mixed> $scopeParams */
    private function applyMutation(
        string $id,
        string $scopeWhere,
        array $scopeParams,
        AddressValidatedMutationPlan $plan,
    ): void {
        $statement = $this->prepare(
            'UPDATE address_entity SET '.$plan->setClause().' WHERE id = :id AND '.$scopeWhere
        );
        $ok = $statement->execute(array_merge([':id' => $id], $plan->params, $scopeParams));

        if (!$ok) {
            throw new \RuntimeException('apply_failed');
        }
        if ($statement->rowCount() < 1) {
            throw new \RuntimeException('not_found');
        }
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
        $statement = $this->pdo->prepare($sql);
        if (false === $statement) {
            throw new \RuntimeException('prepare_failed');
        }

        return $statement;
    }
}
