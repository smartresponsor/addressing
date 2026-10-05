<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\Address\AddressCountryEntity;
use App\Addressing\RepositoryInterface\AddressCountryEntityRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Provides Doctrine persistence access for canonical Addressing country reference entities.
 *
 * @extends ServiceEntityRepository<AddressCountryEntity>
 */
final class AddressCountryEntityRepository extends ServiceEntityRepository implements AddressCountryEntityRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AddressCountryEntity::class);
    }
}
