<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\RateLimitEntity;
use App\Addressing\RepositoryInterface\AddressRateLimitRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAddressRateLimitRepository implements AddressRateLimitRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function checkAndIncrement(string $client, string $key, int $effectiveLimit): bool
    {
        $now = time();
        $minWindow = $now - 60;
        $this->entityManager->beginTransaction();

        try {
            $entity = $this->entityManager->find(RateLimitEntity::class, ['client' => $client, 'rkey' => $key]);
            if (!$entity instanceof RateLimitEntity) {
                $entity = (new RateLimitEntity())
                    ->setClient($client)
                    ->setRkey($key)
                    ->setTs($now)
                    ->setCnt(1);
                $this->entityManager->persist($entity);
                $this->entityManager->flush();
                $this->entityManager->commit();

                return true;
            }

            if ($entity->getTs() < $minWindow) {
                $entity->setTs($now)->setCnt(1);
                $this->entityManager->flush();
                $this->entityManager->commit();

                return true;
            }

            if ($entity->getCnt() + 1 > $effectiveLimit) {
                $this->entityManager->rollback();

                return false;
            }

            $entity->setCnt($entity->getCnt() + 1);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return true;
        } catch (\Throwable $throwable) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            throw $throwable;
        }
    }
}
