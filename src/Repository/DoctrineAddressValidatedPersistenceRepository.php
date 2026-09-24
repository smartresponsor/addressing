<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\Entity\AddressEvidenceSnapshotEntity;
use App\Addressing\Entity\AddressOutboxEntity;
use App\Addressing\RepositoryInterface\AddressValidatedPersistenceRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAddressValidatedPersistenceRepository implements AddressValidatedPersistenceRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findScopedAddress(string $id, ?string $ownerId, ?string $vendorId): ?AddressEntity
    {
        $criteria = ['id' => $id, 'deletedAt' => null];
        if (null !== $ownerId) {
            $criteria['ownerId'] = $ownerId;
        }
        if (null !== $vendorId) {
            $criteria['vendorId'] = $vendorId;
        }

        $entity = $this->entityManager->getRepository(AddressEntity::class)->findOneBy($criteria);

        return $entity instanceof AddressEntity ? $entity : null;
    }

    public function beginTransaction(): void
    {
        $this->entityManager->beginTransaction();
    }

    public function commit(): void
    {
        $this->entityManager->commit();
    }

    public function rollbackIfActive(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
    }

    public function persistEvidenceSnapshot(AddressEvidenceSnapshotEntity $snapshot): void
    {
        $this->entityManager->persist($snapshot);
    }

    public function persistOutbox(AddressOutboxEntity $outbox): void
    {
        $this->entityManager->persist($outbox);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
