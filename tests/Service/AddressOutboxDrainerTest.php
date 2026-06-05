<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Service;

use App\Entity\AddressOutboxEntity;
use App\Service\Application\AddressOutboxDrainerService;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class AddressOutboxDrainerTest extends TestCase
{
    public function testMultipleDrainersDoNotDoublePublish(): void
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'address-outbox-');
        static::assertNotFalse($dbFile);

        $entityManager1 = TestDatabase::createSqliteEntityManager($dbFile, [AddressOutboxEntity::class]);
        $entityManager2 = TestDatabase::createSqliteEntityManager($dbFile, [AddressOutboxEntity::class]);

        $this->insertOutbox($entityManager1, 'AddressCreated', 1, ['id' => 'addr-1']);
        $this->insertOutbox($entityManager1, 'AddressCreated', 1, ['id' => 'addr-2']);

        $published = [];

        $drainer2 = new AddressOutboxDrainerService(
            $entityManager2,
            function (
                string $url,
                array $data,
                int $retryLimit,
                int $timeoutSec,
                int $backoffMs,
                ?string &$error,
            ) use (&$published): bool {
                $published[] = $data['payload']['id'] ?? null;

                return true;
            }
        );

        $drainer1 = new AddressOutboxDrainerService(
            $entityManager1,
            function (
                string $url,
                array $data,
                int $retryLimit,
                int $timeoutSec,
                int $backoffMs,
                ?string &$error,
            ) use (&$published, $drainer2): bool {
                $published[] = $data['payload']['id'] ?? null;
                $drainer2->drain('http://example.test', 10, 0, 1, 0);

                return true;
            }
        );

        $drainer1->drain('http://example.test', 1, 0, 1, 0);

        sort($published);
        static::assertSame(['addr-1', 'addr-2'], $published);

        $entityManager1->clear();
        /** @var list<AddressOutboxEntity> $rows */
        $rows = $entityManager1->getRepository(AddressOutboxEntity::class)->findBy([], ['id' => 'ASC']);
        static::assertCount(2, $rows);
        static::assertNotNull($rows[0]->getPublishedAt());
        static::assertNotNull($rows[1]->getPublishedAt());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertOutbox(\Doctrine\ORM\EntityManagerInterface $entityManager, string $eventName, int $eventVersion, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        static::assertIsString($json);

        $entity = (new AddressOutboxEntity())
            ->setEventName($eventName)
            ->setEventVersion($eventVersion)
            ->setPayload($json)
            ->setCreatedAt(new \DateTimeImmutable('now'));

        $entityManager->persist($entity);
        $entityManager->flush();
    }
}
