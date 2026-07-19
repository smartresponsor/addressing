<?php

declare(strict_types=1);

namespace App\Repository\Address;

use App\Entity\Address\AddressComponentEntity;
use App\RepositoryInterface\Address\AddressComponentEntityRepositoryInterface;
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
