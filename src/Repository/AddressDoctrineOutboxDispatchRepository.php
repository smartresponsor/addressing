<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\AddressOutboxEntity;
use App\Addressing\RepositoryInterface\AddressOutboxDispatchRepositoryInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Reserves Addressing outbox rows for dispatch and records publish success or failure state.
 */
final readonly class AddressDoctrineOutboxDispatchRepository implements AddressOutboxDispatchRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** Reserve unpublished outbox rows only after a successful conditional database claim. */
    public function reserve(string $lockId, int $limit): array
    {
        $this->entityManager->beginTransaction();

        try {
            /** @var list<array{id: int, event_name: string, event_version: int, payload: string}> $candidates */
            $candidates = $this->entityManager->createQueryBuilder()
                ->select('e.id AS id', 'e.eventName AS event_name', 'e.eventVersion AS event_version', 'e.payload AS payload')
                ->from(AddressOutboxEntity::class, 'e')
                ->where('e.publishedAt IS NULL AND e.lockedAt IS NULL')
                ->orderBy('e.id', 'ASC')
                ->setMaxResults(max(1, $limit))
                ->getQuery()
                ->getArrayResult();

            $rows = [];
            $now = new \DateTimeImmutable('now');
            foreach ($candidates as $candidate) {
                $claimed = $this->entityManager->createQueryBuilder()
                    ->update(AddressOutboxEntity::class, 'e')
                    ->set('e.lockedAt', ':now')
                    ->set('e.lockedBy', ':owner')
                    ->where('e.id = :id AND e.publishedAt IS NULL AND e.lockedAt IS NULL')
                    ->setParameter('id', $candidate['id'])
                    ->setParameter('now', $now, Types::DATETIMETZ_IMMUTABLE)
                    ->setParameter('owner', $lockId)
                    ->getQuery()
                    ->execute();

                if (1 === $claimed) {
                    $rows[] = $candidate;
                }
            }

            $this->entityManager->commit();

            return $rows;
        } catch (\Throwable $throwable) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            throw $throwable;
        }
    }

    /** Mark a currently reserved outbox row published and clear its claim atomically. */
    public function markPublished(int $id): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(AddressOutboxEntity::class, 'e')
            ->set('e.publishedAt', ':now')
            ->set('e.lockedAt', 'NULL')
            ->set('e.lockedBy', 'NULL')
            ->set('e.publishedAttempt', 'e.publishedAttempt + 1')
            ->set('e.lastError', 'NULL')
            ->where('e.id = :id AND e.publishedAt IS NULL AND e.lockedAt IS NOT NULL')
            ->setParameter('id', $id)
            ->setParameter('now', new \DateTimeImmutable('now'), Types::DATETIMETZ_IMMUTABLE)
            ->getQuery()
            ->execute();
    }

    /** Release a currently reserved unpublished row and increment its attempt counter atomically. */
    public function markDispatchFailure(int $id, ?string $error): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(AddressOutboxEntity::class, 'e')
            ->set('e.lockedAt', 'NULL')
            ->set('e.lockedBy', 'NULL')
            ->set('e.publishedAttempt', 'e.publishedAttempt + 1')
            ->set('e.lastError', ':error')
            ->where('e.id = :id AND e.publishedAt IS NULL AND e.lockedAt IS NOT NULL')
            ->setParameter('id', $id)
            ->setParameter('error', $error)
            ->getQuery()
            ->execute();
    }
}
