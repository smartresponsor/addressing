<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\AddressOutboxEntity;
use App\Addressing\RepositoryInterface\AddressOutboxDispatchRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAddressOutboxDispatchRepository implements AddressOutboxDispatchRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function reserve(string $lockId, int $limit): array
    {
        $this->entityManager->beginTransaction();

        try {
            /** @var list<AddressOutboxEntity> $entities */
            $entities = $this->entityManager->getRepository(AddressOutboxEntity::class)->findBy(
                ['publishedAt' => null, 'lockedAt' => null],
                ['id' => 'ASC'],
                max(1, $limit),
            );

            if ([] === $entities) {
                $this->entityManager->commit();

                return [];
            }

            $rows = [];
            $now = new \DateTimeImmutable('now');
            foreach ($entities as $entity) {
                $entity->setLockedAt($now);
                $entity->setLockedBy($lockId);
                $rows[] = [
                    'id' => $entity->getId(),
                    'event_name' => $entity->getEventName(),
                    'event_version' => $entity->getEventVersion(),
                    'payload' => $entity->getPayload(),
                ];
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $rows;
        } catch (\Throwable $throwable) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            throw $throwable;
        }
    }

    public function markPublished(int $id): void
    {
        $entity = $this->entityManager->find(AddressOutboxEntity::class, $id);
        if (!$entity instanceof AddressOutboxEntity) {
            return;
        }

        $entity
            ->setPublishedAt(new \DateTimeImmutable('now'))
            ->setLockedAt(null)
            ->setLockedBy(null)
            ->setPublishedAttempt($entity->getPublishedAttempt() + 1)
            ->setLastError(null);

        $this->entityManager->flush();
    }

    public function markDispatchFailure(int $id, ?string $error): void
    {
        $entity = $this->entityManager->find(AddressOutboxEntity::class, $id);
        if (!$entity instanceof AddressOutboxEntity) {
            return;
        }

        $entity
            ->setLockedAt(null)
            ->setLockedBy(null)
            ->setPublishedAttempt($entity->getPublishedAttempt() + 1)
            ->setLastError($error);

        $this->entityManager->flush();
    }
}
