<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Entity\RateLimitEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AddressRateLimiter
{
    public function __construct(private ?EntityManagerInterface $entityManager, private int $limitPerMinute = 60, private int $burst = 30)
    {
    }

    public function check(string $client, string $key): bool
    {
        if (!$this->entityManager instanceof EntityManagerInterface) {
            return true; // no DB — no rate limiting
        }

        $entityManager = $this->entityManager;
        $now = time();
        $minWindow = $now - 60;

        $entityManager->beginTransaction();
        try {
            $entity = $entityManager->find(RateLimitEntity::class, ['client' => $client, 'rkey' => $key]);
            if (!$entity instanceof RateLimitEntity) {
                $entity = (new RateLimitEntity())
                    ->setClient($client)
                    ->setRkey($key)
                    ->setTs($now)
                    ->setCnt(1);
                $entityManager->persist($entity);
                $entityManager->flush();
                $entityManager->commit();

                return true;
            }

            if ($entity->getTs() < $minWindow) {
                $entity->setTs($now);
                $entity->setCnt(1);
                $entityManager->flush();
                $entityManager->commit();

                return true;
            }

            $effectiveLimit = $this->limitPerMinute + $this->burst;
            if ($entity->getCnt() + 1 > $effectiveLimit) {
                $entityManager->rollback();

                return false;
            }

            $entity->setCnt($entity->getCnt() + 1);
            $entityManager->flush();
            $entityManager->commit();

            return true;
        } catch (\Throwable $throwable) {
            if ($entityManager->getConnection()->isTransactionActive()) {
                $entityManager->rollback();
            }

            throw $throwable;
        }
    }
}
