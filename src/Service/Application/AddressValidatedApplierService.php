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
use DateTimeImmutable;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;
use Override;

final readonly class AddressValidatedApplierService implements AddressValidatedApplierServiceInterface
{
    public function __construct(
        private PDO $pdo,
        private AddressTenantScopeSqlHelper $addressTenantScopeSqlHelper,
        private AddressValidatedPayloadFactory $addressValidatedPayloadFactory,
        private AddressValidatedMutationPlanBuilder $addressValidatedMutationPlanBuilder,
        private AddressEvidenceSnapshotWriter $addressEvidenceSnapshotWriter,
        private AddressOutboxWriter $addressOutboxWriter,
    ) {
    }

    #[Override]
    public function apply(string $id, AddressValidated $address_validated, ?string $owner_id = null, ?string $vendor_id = null): void
    {
        $fingerprint = $address_validated->fingerprint();
        $now = new DateTimeImmutable('now');
        $validated_at = $address_validated->validatedAt ?? $now;
        $scope_params = $this->addressTenantScopeSqlHelper->params($owner_id, $vendor_id);
        $scope_where = $this->addressTenantScopeSqlHelper->whereClause($owner_id, $vendor_id);
        $lock_clause = $this->isPgsql() ? ' FOR UPDATE' : '';

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->prepare('SELECT validation_fingerprint FROM address_entity WHERE id = :id AND '.$scope_where.$lock_clause);
            $stmt->execute(array_merge([':id' => $id], $scope_params));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                $this->pdo->rollBack();
                throw new RuntimeException('not_found');
            }

            /** @var array<string, mixed> $row */
            $prev = $row['validation_fingerprint'] ?? null;
            if (is_string($prev) && '' !== $prev && $prev === $fingerprint) {
                $this->pdo->commit();

                return;
            }

            $plan = $this->addressValidatedMutationPlanBuilder->build($id, $address_validated, $fingerprint, $now, $validated_at);

            $sql = 'UPDATE address_entity SET '.$plan->setClause().' WHERE id = :id AND '.$scope_where;
            $stmt = $this->prepare($sql);
            $ok = $stmt->execute(array_merge($plan->params, $scope_params));

            if (!$ok) {
                $this->pdo->rollBack();
                throw new RuntimeException('apply_failed');
            }
            if ($stmt->rowCount() < 1) {
                $this->pdo->rollBack();
                throw new RuntimeException('not_found');
            }

            $evidence_snapshot_id = $this->addressEvidenceSnapshotWriter->write(
                $id,
                $owner_id,
                $vendor_id,
                $address_validated,
                $plan->lastValidationStatus,
                $plan->lastValidationScore,
                $plan->normalizedSnapshot,
                $plan->providerDigest,
            );

            $this->addressOutboxWriter->write(
                $this->addressValidatedPayloadFactory->outboxPayload(
                    $id,
                    $owner_id,
                    $vendor_id,
                    $fingerprint,
                    $address_validated,
                    $validated_at,
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
                    $evidence_snapshot_id,
                    $plan->providerDigest,
                )
            );

            $this->pdo->commit();
        } catch (RuntimeException $e) {
            $this->rollbackIfActive();
            throw $e;
        } catch (Throwable) {
            $this->rollbackIfActive();
            throw new RuntimeException('apply_failed');
        }
    }

    private function isPgsql(): bool
    {
        $driver_attr = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return is_string($driver_attr) && 'pgsql' === $driver_attr;
    }

    private function rollbackIfActive(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
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
