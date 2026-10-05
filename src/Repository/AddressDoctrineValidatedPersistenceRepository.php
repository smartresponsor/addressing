<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\Entity\AddressEvidenceSnapshotEntity;
use App\Addressing\Entity\AddressOutboxEntity;
use App\Addressing\RepositoryInterface\AddressValidatedPersistenceRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Provides the transaction and persistence primitives used to store validated Addressing mutations atomically.
 */
final readonly class AddressDoctrineValidatedPersistenceRepository implements AddressValidatedPersistenceRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** Return one non-deleted address entity constrained by the supplied owner or vendor scope. */
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

    /** Begin the Doctrine transaction that groups one validated Addressing persistence workflow. */
    public function beginTransaction(): void
    {
        $this->entityManager->beginTransaction();
    }

    /** Commit the active validated Addressing persistence transaction. */
    public function commit(): void
    {
        $this->entityManager->commit();
    }

    /** Roll back the validated persistence transaction only when Doctrine still reports it active. */
    public function rollbackIfActive(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
    }

    /** Stage one validated evidence snapshot entity in the current Doctrine unit of work. */
    public function persistEvidenceSnapshot(AddressEvidenceSnapshotEntity $snapshot): void
    {
        $this->entityManager->persist($snapshot);
    }

    /** Stage one validated Addressing outbox entity in the current Doctrine unit of work. */
    public function persistOutbox(AddressOutboxEntity $outbox): void
    {
        $this->entityManager->persist($outbox);
    }

    /** Flush all staged validated Addressing persistence changes through the active Doctrine unit of work. */
    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
