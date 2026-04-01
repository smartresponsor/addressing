<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Application;

use App\Contract\Message\AddressValidated;
use App\Integration\Persistence\AddressEvidenceSnapshotWriter;
use App\Integration\Persistence\AddressOutboxWriter;
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
        private AddressEvidenceSnapshotWriter $addressEvidenceSnapshotWriter,
        private AddressOutboxWriter $addressOutboxWriter,
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

            $evidenceSnapshotId = $this->addressEvidenceSnapshotWriter->write(
                $id,
                $ownerId,
                $vendorId,
                $addressValidated,
                $plan->lastValidationStatus,
                $plan->lastValidationScore,
                $plan->normalizedSnapshot,
                $plan->providerDigest,
            );

            $this->addressOutboxWriter->write(
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
