<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Contract\AddressEvidenceSnapshotInterface;
use App\Addressing\Contract\AddressInterface;
use App\Addressing\Entity\AddressEntity;
use App\Addressing\Entity\AddressEvidenceSnapshotEntity;
use App\Addressing\RepositoryInterface\AddressEvidenceRepositoryInterface;

/**
 * Persists and reads Addressing validation evidence snapshots within tenant-scoped Doctrine queries.
 */
final readonly class AddressDoctrineEvidenceRepository extends AddressAbstractDoctrineRepository implements AddressEvidenceRepositoryInterface
{
    /** Persist the current address evidence snapshot when the scoped address exists and carries evidence. */
    #[\Override]
    public function appendEvidenceSnapshot(AddressInterface $address): ?AddressEvidenceSnapshotInterface
    {
        $entity = $this->findDoctrineAddress($address->id(), $address->ownerId(), $address->vendorId());
        if (!$entity instanceof AddressEntity) {
            return null;
        }

        return $this->entityManager->wrapInTransaction(function () use ($address, $entity): ?AddressEvidenceSnapshotInterface {
            $snapshot = $this->appendEvidenceSnapshotInternal($address, $entity);
            $this->entityManager->flush();

            return $snapshot;
        });
    }

    /** Return the most recent evidence snapshot for one non-deleted address inside the requested tenant scope. */
    #[\Override]
    public function getLatestEvidenceSnapshot(string $addressId, ?string $ownerId, ?string $vendorId): ?AddressEvidenceSnapshotInterface
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('s', 'a')
            ->from(AddressEvidenceSnapshotEntity::class, 's')
            ->join('s.address', 'a')
            ->where('a.id = :addressId')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('addressId', $addressId)
            ->addSelect('CASE WHEN s.validatedAt IS NULL THEN s.createdAt ELSE s.validatedAt END AS HIDDEN effectiveAt')
            ->orderBy('effectiveAt', 'DESC')
            ->addOrderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1);
        $this->applyTenantScope($queryBuilder, 'a', $ownerId, $vendorId);

        $entity = $queryBuilder->getQuery()->getOneOrNullResult();

        return $entity instanceof AddressEvidenceSnapshotEntity ? $this->mapSnapshotEntity($entity) : null;
    }

    /** Return a cursor-paginated evidence history ordered newest-first within the requested tenant scope. */
    #[\Override]
    public function findEvidenceHistoryPage(string $addressId, ?string $ownerId, ?string $vendorId, int $limit, ?string $cursor): array
    {
        $effectiveLimit = max(1, min(200, $limit));
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('s', 'a')
            ->from(AddressEvidenceSnapshotEntity::class, 's')
            ->join('s.address', 'a')
            ->where('a.id = :addressId')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('addressId', $addressId)
            ->orderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults($effectiveLimit + 1);
        $this->applyTenantScope($queryBuilder, 'a', $ownerId, $vendorId);

        if (null !== $cursor) {
            [$cursorCreatedAt, $cursorId] = $this->decodeEvidenceCursor($cursor);
            $queryBuilder->andWhere('(s.createdAt < :cursorCreatedAt OR (s.createdAt = :cursorCreatedAt AND s.id < :cursorId))')
                ->setParameter('cursorCreatedAt', new \DateTimeImmutable($cursorCreatedAt))
                ->setParameter('cursorId', $cursorId);
        }

        /** @var list<AddressEvidenceSnapshotEntity> $entities */
        $entities = $queryBuilder->getQuery()->getResult();
        $items = [];
        $nextCursor = null;

        foreach ($entities as $index => $entity) {
            if ($index >= $effectiveLimit) {
                $lastIncluded = $entities[$index - 1] ?? null;
                if ($lastIncluded instanceof AddressEvidenceSnapshotEntity) {
                    $nextCursor = $this->encodeEvidenceCursor($lastIncluded->getCreatedAt()->format(DATE_ATOM), $lastIncluded->getId());
                }
                break;
            }

            $items[] = $this->mapSnapshotEntity($entity);
        }

        return ['items' => $items, 'nextCursor' => $nextCursor];
    }
}
