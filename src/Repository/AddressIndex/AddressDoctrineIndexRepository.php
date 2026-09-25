<?php

declare(strict_types=1);

namespace App\Addressing\Repository\AddressIndex;

use App\Addressing\Entity\AddressIndexEntity;
use App\Addressing\Projection\AddressIndex\AddressIndexRecord;
use App\Addressing\RepositoryInterface\AddressIndex\AddressIndexRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AddressDoctrineIndexRepository implements AddressIndexRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function upsert(AddressIndexRecord $indexRecord): void
    {
        $entity = $this->entityManager->find(AddressIndexEntity::class, $indexRecord->digest);
        if (!$entity instanceof AddressIndexEntity) {
            $entity = new AddressIndexEntity();
            $entity->setDigest($indexRecord->digest);
            $this->entityManager->persist($entity);
        }

        $entity
            ->setLine1($indexRecord->line1)
            ->setLine2($indexRecord->line2)
            ->setCity($indexRecord->city)
            ->setRegion($indexRecord->region)
            ->setPostal($indexRecord->postal)
            ->setCountry($indexRecord->country)
            ->setLat($indexRecord->lat)
            ->setLon($indexRecord->lon)
            ->setDisplay($indexRecord->display)
            ->setProvider($indexRecord->provider)
            ->setConfidence($indexRecord->confidence)
            ->setGeoKey($indexRecord->geoKey)
            ->setCreatedAt($indexRecord->createdAt)
            ->setUpdatedAt($indexRecord->updatedAt);

        $this->entityManager->flush();
    }

    #[\Override]
    public function getByDigest(string $digest): ?AddressIndexRecord
    {
        $entity = $this->entityManager->find(AddressIndexEntity::class, $digest);

        return $entity instanceof AddressIndexEntity ? $this->fromEntity($entity) : null;
    }

    /**
     * @return array<AddressIndexRecord>
     */
    #[\Override]
    public function search(string $prefix, ?string $country = null, int $limit = 20): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('i')
            ->from(AddressIndexEntity::class, 'i')
            ->where('i.line1 LIKE :q OR i.city LIKE :q OR i.region LIKE :q OR i.postal LIKE :q')
            ->setParameter('q', $prefix.'%')
            ->orderBy('i.updatedAt', 'DESC')
            ->setMaxResults(max(1, $limit));

        if (null !== $country && '' !== trim($country)) {
            $queryBuilder->andWhere('i.country = :country')
                ->setParameter('country', strtoupper($country));
        }

        /** @var list<AddressIndexEntity> $entities */
        $entities = $queryBuilder->getQuery()->getResult();

        return array_map(
            $this->fromEntity(...),
            $entities,
        );
    }

    private function fromEntity(AddressIndexEntity $entity): AddressIndexRecord
    {
        return new AddressIndexRecord(
            digest: $entity->getDigest(),
            line1: $entity->getLine1(),
            line2: $entity->getLine2(),
            city: $entity->getCity(),
            region: $entity->getRegion(),
            postal: $entity->getPostal(),
            country: $entity->getCountry(),
            lat: $entity->getLat(),
            lon: $entity->getLon(),
            display: $entity->getDisplay(),
            provider: $entity->getProvider(),
            confidence: $entity->getConfidence(),
            geoKey: $entity->getGeoKey(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }
}
