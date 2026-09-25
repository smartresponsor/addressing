<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\Address\AddressComponentEntity;
use App\Addressing\RepositoryInterface\AddressComponentEntityRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AddressComponentEntity> */
final class AddressComponentEntityRepository extends ServiceEntityRepository implements AddressComponentEntityRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AddressComponentEntity::class);
    }
}
