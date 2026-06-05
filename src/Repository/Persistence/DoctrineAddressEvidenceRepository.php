<?php

declare(strict_types=1);

namespace App\Repository\Persistence;

use App\Entity\AddressEntity;
use App\Entity\AddressEvidenceSnapshotEntity;
use App\EntityInterface\Record\AddressEvidenceSnapshotInterface;
use App\EntityInterface\Record\AddressInterface;
use App\RepositoryInterface\Persistence\AddressEvidenceRepositoryInterface;

final readonly class DoctrineAddressEvidenceRepository extends AbstractDoctrineAddressRepository implements AddressEvidenceRepositoryInterface
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
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('s', 'a')
            ->from(AddressEvidenceSnapshotEntity::class, 's')
            ->join('s.address', 'a')
            ->where('a.id = :addressId')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('addressId', $addressId)
            ->orderBy('COALESCE(s.validatedAt, s.createdAt)', 'DESC')
            ->addOrderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1);
        $this->applyTenantScope($qb, 'a', $ownerId, $vendorId);

        $entity = $qb->getQuery()->getOneOrNullResult();

        return $entity instanceof AddressEvidenceSnapshotEntity ? $this->mapSnapshotEntity($entity) : null;
    }

    #[\Override]
    public function findEvidenceHistoryPage(string $addressId, ?string $ownerId, ?string $vendorId, int $limit, ?string $cursor): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('s', 'a')
            ->from(AddressEvidenceSnapshotEntity::class, 's')
            ->join('s.address', 'a')
            ->where('a.id = :addressId')
            ->andWhere('a.deletedAt IS NULL')
            ->setParameter('addressId', $addressId)
            ->orderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(max(1, min(200, $limit)) + 1);
        $this->applyTenantScope($qb, 'a', $ownerId, $vendorId);

        if (null !== $cursor) {
            [$cursorCreatedAt, $cursorId] = $this->decodeEvidenceCursor($cursor);
            $qb->andWhere('(s.createdAt < :cursorCreatedAt OR (s.createdAt = :cursorCreatedAt AND s.id < :cursorId))')
                ->setParameter('cursorCreatedAt', new \DateTimeImmutable($cursorCreatedAt))
                ->setParameter('cursorId', $cursorId);
        }

        /** @var list<AddressEvidenceSnapshotEntity> $entities */
        $entities = $qb->getQuery()->getResult();
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
