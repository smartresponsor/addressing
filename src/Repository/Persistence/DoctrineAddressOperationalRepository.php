<?php

declare(strict_types=1);

namespace App\Repository\Persistence;

use App\Entity\AddressEntity;
use App\RepositoryInterface\Persistence\AddressOperationalRepositoryInterface;

final readonly class DoctrineAddressOperationalRepository extends AbstractDoctrineAddressRepository implements AddressOperationalRepositoryInterface
{
    #[\Override]
    public function patchOperational(string $id, ?string $ownerId, ?string $vendorId, array $patch): bool
    {
        $this->ensureTenantScope($ownerId, $vendorId);

        $entity = $this->findDoctrineAddress($id, $ownerId, $vendorId);
        if (!$entity instanceof AddressEntity) {
            return false;
        }

        $normalized = $this->normalizeOperationalPatch($entity->getId(), $entity->getGovernanceStatus(), $patch);
        if ([] === $normalized) {
            return false;
        }

        $this->assertGovernanceTargetsExist($normalized, $ownerId, $vendorId);
        $updatedAt = new \DateTimeImmutable('now');

        $this->entityManager->wrapInTransaction(function () use ($entity, $normalized, $updatedAt, $id, $ownerId, $vendorId): void {
            $this->applyOperationalPatchToEntity($entity, $normalized, $updatedAt);
            $this->entityManager->flush();

            $governanceStatus = isset($normalized['governance_status']) && is_string($normalized['governance_status'])
                ? $normalized['governance_status']
                : null;
            $governanceLinkId = null === $governanceStatus
                ? null
                : match ($governanceStatus) {
                    'duplicate' => $normalized['duplicate_of_id'] ?? null,
                    'superseded' => $normalized['superseded_by_id'] ?? null,
                    'alias' => $normalized['alias_of_id'] ?? null,
                    'conflict' => $normalized['conflict_with_id'] ?? null,
                    default => null,
                };

            $this->appendOutbox('AddressOperationalPatched', [
                'id' => $id,
                'ownerId' => $ownerId,
                'vendorId' => $vendorId,
                'patchedFields' => array_keys($normalized),
                'governanceStatus' => $governanceStatus,
                'governanceLinkId' => $governanceLinkId,
                'revalidationDueAt' => $normalized['revalidation_due_at'] ?? null,
                'revalidationPolicy' => $normalized['revalidation_policy'] ?? null,
                'lastValidationStatus' => $normalized['last_validation_status'] ?? null,
                'updatedAt' => $updatedAt->format(DATE_ATOM),
            ]);
        });

        return true;
    }
}
