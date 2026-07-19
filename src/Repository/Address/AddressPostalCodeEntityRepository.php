<?php

declare(strict_types=1);

namespace App\Repository\Address;

use App\Entity\Address\AddressPostalCodeEntity;
use App\RepositoryInterface\Address\AddressPostalCodeEntityRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AddressPostalCodeEntity> */
final class AddressPostalCodeEntityRepository extends ServiceEntityRepository implements AddressPostalCodeEntityRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AddressPostalCodeEntity::class);
    }
}
