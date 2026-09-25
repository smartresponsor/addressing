<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\EntityInterface\Record\AddressInterface;
use App\Addressing\RepositoryInterface\AddressWriteRepositoryInterface;

final readonly class AddressDoctrineWriteRepository extends AddressAbstractDoctrineRepository implements AddressWriteRepositoryInterface
{
    #[\Override]
    public function create(AddressInterface $address): void
    {
        $addressEntity = $this->mapper->toDoctrine($this->asRecord($address));

        $this->entityManager->wrapInTransaction(function () use ($addressEntity, $address): void {
            $this->entityManager->persist($addressEntity);
            $evidenceSnapshot = $this->appendEvidenceSnapshotInternal($address, $addressEntity);
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

        $addressEntity = $this->mapper->toDoctrine($this->asRecord($address));
        $this->copyAddressEntityState($existing, $addressEntity);

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
