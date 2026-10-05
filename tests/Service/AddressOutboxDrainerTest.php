<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Service;

use App\Addressing\Entity\AddressOutboxEntity;
use App\Addressing\Message\AddressOutboxEventMessage;
use App\Addressing\Repository\AddressDoctrineOutboxDispatchRepository;
use App\Addressing\Service\Application\AddressOutboxDrainerService;
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
            new AddressDoctrineOutboxDispatchRepository($entityManager2),
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
            },
        );

        $drainer1 = new AddressOutboxDrainerService(
            new AddressDoctrineOutboxDispatchRepository($entityManager1),
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
            },
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

    public function testOutboxEventMessageDecoratesSupportedEventsAndRejectsUnknownNames(): void
    {
        $versions = AddressOutboxEventMessage::eventVersions();
        self::assertSame(1, $versions['AddressCreated']);
        self::assertSame(1, AddressOutboxEventMessage::eventVersion('AddressValidatedApplied'));

        $decorated = AddressOutboxEventMessage::decoratePayload('AddressCreated', ['id' => 'addr-1']);
        self::assertSame('AddressCreated', $decorated['eventName']);
        self::assertSame(AddressOutboxEventMessage::SCHEMA_VERSION, $decorated['schemaVersion']);
        self::assertSame(1, $decorated['eventVersion']);
        self::assertSame('addr-1', $decorated['id']);
        self::assertIsString($decorated['occurredAt']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown_address_event_name');
        AddressOutboxEventMessage::eventVersion('UnknownAddressEvent');
    }

    public function testDispatchRepositoryReleasesFailuresAndPublishesRetries(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([AddressOutboxEntity::class]);
        $repository = new AddressDoctrineOutboxDispatchRepository($entityManager);
        $this->insertOutbox($entityManager, 'AddressUpdated', 2, ['id' => 'addr-9']);

        $reserved = $repository->reserve('worker-1', 0);
        self::assertCount(1, $reserved);
        self::assertSame('AddressUpdated', $reserved[0]['event_name']);
        self::assertSame(2, $reserved[0]['event_version']);

        $id = $reserved[0]['id'];
        self::assertIsInt($id);
        $entityManager->clear();
        $locked = $entityManager->find(AddressOutboxEntity::class, $id);
        self::assertInstanceOf(AddressOutboxEntity::class, $locked);
        self::assertSame('worker-1', $locked->getLockedBy());
        self::assertNotNull($locked->getLockedAt());

        $repository->markDispatchFailure($id, 'network_error');
        $entityManager->clear();
        $failed = $entityManager->find(AddressOutboxEntity::class, $id);
        self::assertInstanceOf(AddressOutboxEntity::class, $failed);
        self::assertNull($failed->getLockedAt());
        self::assertNull($failed->getLockedBy());
        self::assertSame(1, $failed->getPublishedAttempt());
        self::assertSame('network_error', $failed->getLastError());

        $retry = $repository->reserve('worker-2', 10);
        self::assertCount(1, $retry);
        $repository->markPublished($id);
        $entityManager->clear();
        $published = $entityManager->find(AddressOutboxEntity::class, $id);
        self::assertInstanceOf(AddressOutboxEntity::class, $published);
        self::assertNotNull($published->getPublishedAt());
        self::assertNull($published->getLockedAt());
        self::assertNull($published->getLockedBy());
        self::assertSame(2, $published->getPublishedAttempt());
        self::assertNull($published->getLastError());
        self::assertSame([], $repository->reserve('worker-3', 10));

        $repository->markPublished(999999);
        $repository->markDispatchFailure(999999, 'ignored');
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
