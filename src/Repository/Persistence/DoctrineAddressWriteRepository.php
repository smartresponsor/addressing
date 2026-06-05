<?php

declare(strict_types=1);

namespace App\Repository\Persistence;

use App\Entity\AddressEntity;
use App\EntityInterface\Record\AddressInterface;
use App\RepositoryInterface\Persistence\AddressWriteRepositoryInterface;

final readonly class DoctrineAddressWriteRepository extends AbstractDoctrineAddressRepository implements AddressWriteRepositoryInterface
{
    #[\Override]
    public function create(AddressInterface $address): void
    {
        $entity = $this->mapper->toDoctrine($this->asRecord($address));

        $this->entityManager->wrapInTransaction(function () use ($entity, $address): void {
            $this->entityManager->persist($entity);
            $evidenceSnapshot = $this->appendEvidenceSnapshotInternal($address, $entity);
            $this->entityManager->flush();
            $this->appendOutbox('AddressCreated', [
                'id' => $address->id(),
                'ownerId' => $address->ownerId(),
                'vendorId' => $address->vendorId(),
                'countryCode' => $address->countryCode(),
                'createdAt' => $address->createdAt(),
                'sourceType' => $address->sourceType(),
                'validationStatus' => $address->validationStatus(),
                'hasEvidence' => $this->hasEvidence($address),
                'governanceStatus' => $address->governanceStatus(),
                'governanceLinkId' => $this->governanceLinkId($address),
                'revalidationDueAt' => $address->revalidationDueAt(),
                'revalidationPolicy' => $address->revalidationPolicy(),
                'lastValidationStatus' => $address->lastValidationStatus(),
                'evidenceSnapshotId' => $evidenceSnapshot?->id(),
            ]);
        });
    }

    #[\Override]
    public function update(AddressInterface $address): void
    {
        $existing = $this->findDoctrineAddress($address->id(), $address->ownerId(), $address->vendorId());
        if (!$existing instanceof AddressEntity) {
            return;
        }

        $incoming = $this->mapper->toDoctrine($this->asRecord($address));
        $this->copyAddressEntityState($existing, $incoming);

        $this->entityManager->wrapInTransaction(function () use ($existing, $address): void {
            $evidenceSnapshot = $this->appendEvidenceSnapshotInternal($address, $existing);
            $this->entityManager->flush();
            $this->appendOutbox('AddressUpdated', [
                'id' => $address->id(),
                'updatedAt' => $address->updatedAt() ?? $this->currentTimestampAtom(),
                'sourceType' => $address->sourceType(),
                'validationStatus' => $address->validationStatus(),
                'hasEvidence' => $this->hasEvidence($address),
                'governanceStatus' => $address->governanceStatus(),
                'governanceLinkId' => $this->governanceLinkId($address),
                'revalidationDueAt' => $address->revalidationDueAt(),
                'revalidationPolicy' => $address->revalidationPolicy(),
                'lastValidationStatus' => $address->lastValidationStatus(),
                'evidenceSnapshotId' => $evidenceSnapshot?->id(),
            ]);
        });
    }

    #[\Override]
    public function delete(string $id, ?string $ownerId, ?string $vendorId): void
    {
        $entity = $this->findDoctrineAddress($id, $ownerId, $vendorId);
        if (!$entity instanceof AddressEntity) {
            return;
        }

        $entity->setDeletedAt(new \DateTimeImmutable('now'));

        $this->entityManager->wrapInTransaction(function () use ($id): void {
            $this->entityManager->flush();
            $this->appendOutbox('AddressDeleted', [
                'id' => $id,
                'deletedAt' => $this->currentTimestampAtom(),
            ]);
        });
    }
}
