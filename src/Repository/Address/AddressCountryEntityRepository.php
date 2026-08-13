<?php

declare(strict_types=1);

namespace App\Addressing\Repository\Address;

use App\Addressing\Entity\Address\AddressCountryEntity;
use App\Addressing\RepositoryInterface\Address\AddressCountryEntityRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AddressCountryEntity> */
final class AddressCountryEntityRepository extends ServiceEntityRepository implements AddressCountryEntityRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AddressCountryEntity::class);
    }
}
