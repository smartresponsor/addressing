<?php

declare(strict_types=1);

namespace App\Addressing\Repository\Address;

use App\Addressing\Entity\Address\AddressCityEntity;
use App\Addressing\RepositoryInterface\Address\AddressCityEntityRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AddressCityEntity> */
final class AddressCityEntityRepository extends ServiceEntityRepository implements AddressCityEntityRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AddressCityEntity::class);
    }
}
