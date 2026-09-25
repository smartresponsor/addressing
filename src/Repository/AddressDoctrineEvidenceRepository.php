<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\Entity\AddressEvidenceSnapshotEntity;
use App\Addressing\EntityInterface\Record\AddressEvidenceSnapshotInterface;
use App\Addressing\EntityInterface\Record\AddressInterface;
use App\Addressing\RepositoryInterface\AddressEvidenceRepositoryInterface;

final readonly class AddressDoctrineEvidenceRepository extends AddressAbstractDoctrineRepository implements AddressEvidenceRepositoryInterface
{
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

    #[\Override]
    public function findEvidenceHistoryPage(string $addressId, ?string $ownerId, ?string $vendorId, int $limit, ?string $cursor): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('s', 'a')
            ->from(AddressEvidenceSnapshotEntity::class, 's')
            ->join('s.address', 'a')
            ->where('a.id = :addressId')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('addressId', $addressId)
            ->orderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(max(1, min(200, $limit)) + 1);
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
            if (!$entity instanceof AddressEvidenceSnapshotEntity) {
                continue;
            }

            if ($index >= $limit) {
                $nextCursor = $this->encodeEvidenceCursor($entity->getCreatedAt()->format(DATE_ATOM), $entity->getId());
                break;
            }

            $items[] = $this->mapSnapshotEntity($entity);
        }

        return ['items' => $items, 'nextCursor' => $nextCursor];
    }
}
