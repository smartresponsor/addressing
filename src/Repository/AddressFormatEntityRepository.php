<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\Address\AddressFormatEntity;
use App\Addressing\RepositoryInterface\AddressFormatEntityRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AddressFormatEntity> */
final class AddressFormatEntityRepository extends ServiceEntityRepository implements AddressFormatEntityRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AddressFormatEntity::class);
    }
}
