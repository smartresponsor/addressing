<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\Address\AddressStreetEntity;
use App\Addressing\RepositoryInterface\AddressStreetEntityRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AddressStreetEntity> */
final class AddressStreetEntityRepository extends ServiceEntityRepository implements AddressStreetEntityRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AddressStreetEntity::class);
    }
}
