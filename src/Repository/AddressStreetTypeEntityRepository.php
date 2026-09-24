<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\Address\AddressStreetTypeEntity;
use App\Addressing\RepositoryInterface\AddressStreetTypeEntityRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AddressStreetTypeEntity> */
final class AddressStreetTypeEntityRepository extends ServiceEntityRepository implements AddressStreetTypeEntityRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AddressStreetTypeEntity::class);
    }
}
