<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\Address\AddressProvinceEntity;
use App\Addressing\RepositoryInterface\AddressProvinceEntityRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AddressProvinceEntity> */
final class AddressProvinceEntityRepository extends ServiceEntityRepository implements AddressProvinceEntityRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AddressProvinceEntity::class);
    }
}
