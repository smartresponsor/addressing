<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\EntityInterface\Record\AddressInterface;
use App\Addressing\RepositoryInterface\AddressReadRepositoryInterface;
use App\Addressing\Value\Persistence\AddressPageCriteria;

final readonly class AddressDoctrineReadRepository extends AddressAbstractDoctrineRepository implements AddressReadRepositoryInterface
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
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('a')
            ->from(AddressEntity::class, 'a')
            ->where('a.deletedAt IS NULL')
            ->orderBy('a.id', 'ASC')
            ->setMaxResults($limit);

        $this->applyAddressScope($queryBuilder, 'a', $criteria->ownerId(), $criteria->vendorId(), $criteria->countryCode(), $criteria->query());
        $this->applyAddressFilters($queryBuilder, 'a', $criteria->filters());
        if (null !== $criteria->cursor() && '' !== $criteria->cursor()) {
            $queryBuilder->andWhere('a.id > :cursor')->setParameter('cursor', $criteria->cursor());
        }

        /** @var list<AddressEntity> $entities */
        $entities = $queryBuilder->getQuery()->getResult();
        $items = array_map(
            $this->mapper->fromDoctrine(...),
            $entities,
        );

        return [
            'items' => $items,
            'nextCursor' => $this->pageCursorFromEntities($entities, $limit),
        ];
    }
}
