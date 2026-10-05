<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Service;

use App\Addressing\Config\Application\AddressOutboxDispatchConfig;
use App\Addressing\Entity\AddressOutboxEntity;
use App\Addressing\Message\AddressOutboxEventMessage;
use App\Addressing\Repository\AddressDoctrineOutboxDispatchRepository;
use App\Addressing\RepositoryInterface\AddressOutboxDispatchRepositoryInterface;
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

    public function testDrainerHelperContractsCoverCoercionRetryAndFailureFormatting(): void
    {
        $repository = $this->createMock(AddressOutboxDispatchRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('reserve')
            ->with('lock-1', 5)
            ->willReturn([['id' => 7]]);
        $repository->expects(self::once())->method('markPublished')->with(7);
        $repository->expects(self::once())->method('markDispatchFailure')->with(8, 'failed');

        $service = new AddressOutboxDrainerService($repository);
        $config = new AddressOutboxDispatchConfig('http://example.test', 2, 3, 10);

        $invoke = static function (string $methodName, array $arguments = []) use ($service): mixed {
            $method = new \ReflectionMethod(AddressOutboxDrainerService::class, $methodName);

            return $method->invokeArgs($service, $arguments);
        };

        self::assertSame([['id' => 7]], $invoke('reserveRows', ['lock-1', 5]));
        self::assertSame('value', $invoke('rowString', [['key' => 'value'], 'key']));
        self::assertNull($invoke('rowString', [['key' => 10], 'key']));
        self::assertSame(9, $invoke('rowInt', [['key' => 9], 'key']));
        self::assertSame(12, $invoke('rowInt', [['key' => '12'], 'key']));
        self::assertSame(0, $invoke('rowInt', [['key' => 'bad'], 'key']));

        self::assertSame([
            'name' => 'AddressCreated',
            'version' => 2,
            'payload' => ['id' => 'addr-1'],
        ], $invoke('eventPayload', [[
            'event_name' => 'AddressCreated',
            'event_version' => '2',
            'payload' => '{"id":"addr-1"}',
        ]]));
        self::assertSame([
            'name' => '',
            'version' => 0,
            'payload' => null,
        ], $invoke('eventPayload', [['payload' => 'not-json']]));

        $lockId = $invoke('lockId', ['http://example.test']);
        self::assertIsString($lockId);
        self::assertNotSame('', $lockId);

        $invoke('markPublished', [7]);
        $invoke('markDispatchFailure', [8, 'failed']);

        self::assertTrue($invoke('isSuccessfulHttpCode', [204, '']));
        self::assertFalse($invoke('isSuccessfulHttpCode', [500, '']));
        self::assertFalse($invoke('isSuccessfulHttpCode', [204, 'socket error']));
        self::assertSame('curl: socket error', $invoke('dispatchFailureMessage', [0, 'socket error', false]));
        self::assertSame('http: 503 unavailable', $invoke('dispatchFailureMessage', [503, '', ' unavailable ']));
        self::assertTrue($invoke('shouldRetry', [2, $config]));
        self::assertFalse($invoke('shouldRetry', [3, $config]));
        self::assertSame(20_000, $invoke('retryDelayMicros', [2, $config]));

        $options = $invoke('curlOptions', [$config, '{}']);
        self::assertSame(true, $options[CURLOPT_RETURNTRANSFER]);
        self::assertSame(true, $options[CURLOPT_POST]);
        self::assertSame('{}', $options[CURLOPT_POSTFIELDS]);
        self::assertSame(3, $options[CURLOPT_TIMEOUT]);

        $error = null;
        $encodedPayload = $invoke('encodedDispatchPayload', [['name' => 'AddressCreated'], &$error]);
        self::assertSame('{"name":"AddressCreated"}', $encodedPayload);

        $sender = static function (
            string $url,
            array $data,
            int $retryLimit,
            int $timeoutSec,
            int $backoffMs,
            ?string &$senderError,
        ): bool {
            $senderError = null;

            return 'http://example.test' === $url
                && 'AddressCreated' === ($data['name'] ?? null)
                && 2 === $retryLimit
                && 3 === $timeoutSec
                && 10 === $backoffMs;
        };
        $senderError = 'old';
        self::assertTrue($invoke('dispatchViaSender', [$sender, $config, ['name' => 'AddressCreated'], &$senderError]));
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
