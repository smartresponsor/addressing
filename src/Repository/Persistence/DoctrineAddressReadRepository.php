<?php

declare(strict_types=1);

namespace App\Repository\Persistence;

use App\Entity\AddressEntity;
use App\EntityInterface\Record\AddressInterface;
use App\RepositoryInterface\Persistence\AddressReadRepositoryInterface;
use App\Value\Persistence\AddressPageCriteria;

final readonly class DoctrineAddressReadRepository extends AbstractDoctrineAddressRepository implements AddressReadRepositoryInterface
{
    #[\Override]
    public function get(string $id, ?string $ownerId, ?string $vendorId): ?AddressInterface
    {
        $entity = $this->findDoctrineAddress($id, $ownerId, $vendorId);

        return $entity instanceof AddressEntity ? $this->mapper->fromDoctrine($entity) : null;
    }

    #[\Override]
    public function findByDedupeKey(string $dedupeKey): ?AddressInterface
    {
        $dedupeKey = trim($dedupeKey);
        if ('' === $dedupeKey) {
            return null;
        }

        $entity = $this->entityManager->getRepository(AddressEntity::class)->findOneBy([
            'dedupeKey' => $dedupeKey,
            'deletedAt' => null,
        ]);

        return $entity instanceof AddressEntity ? $this->mapper->fromDoctrine($entity) : null;
    }

    #[\Override]
    public function findPage(AddressPageCriteria $criteria): array
    {
        $limit = max(1, min(200, $criteria->limit()));
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(AddressEntity::class, 'a')
            ->where('a.deletedAt IS NULL')
            ->orderBy('a.id', 'ASC')
            ->setMaxResults($limit);

        $this->applyAddressScope($qb, 'a', $criteria->ownerId(), $criteria->vendorId(), $criteria->countryCode(), $criteria->query());
        $this->applyAddressFilters($qb, 'a', $criteria->filters());
        if (null !== $criteria->cursor() && '' !== $criteria->cursor()) {
            $qb->andWhere('a.id > :cursor')->setParameter('cursor', $criteria->cursor());
        }

        /** @var list<AddressEntity> $entities */
        $entities = $qb->getQuery()->getResult();
        $items = array_map(
            fn (AddressEntity $entity): AddressInterface => $this->mapper->fromDoctrine($entity),
            $entities,
        );

        return [
            'items' => $items,
            'nextCursor' => $this->pageCursorFromEntities($entities, $limit),
        ];
    }
}
