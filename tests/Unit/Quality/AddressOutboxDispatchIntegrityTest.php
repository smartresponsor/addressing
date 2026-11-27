<?php

declare(strict_types=1);

namespace App\Addressing\Tests\Unit\Quality;

use App\Addressing\RepositoryInterface\AddressOutboxDispatchRepositoryInterface;
use App\Addressing\Service\Application\AddressOutboxDrainerService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AddressOutboxDispatchIntegrityTest extends TestCase
{
    #[DataProvider('invalidPayloadProvider')]
    public function testInvalidPersistedPayloadCannotBePublished(string $payload): void
    {
        $repository = $this->createMock(AddressOutboxDispatchRepositoryInterface::class);
        $repository->expects(self::once())->method('reserve')->willReturn([[
            'id' => 17,
            'event_name' => 'AddressCreated',
            'event_version' => 1,
            'payload' => $payload,
        ]]);
        $repository->expects(self::never())->method('markPublished');
        $repository->expects(self::once())->method('markDispatchFailure')->with(17, 'invalid_outbox_payload');

        $sender = static function (): bool {
            self::fail('Malformed persisted payload must never reach the sender.');
        };

        self::assertSame(1, (new AddressOutboxDrainerService($repository, $sender))->drain('http://example.test'));
    }

    public function testInvalidRowDoesNotBlockFollowingValidEvent(): void
    {
        $repository = $this->createMock(AddressOutboxDispatchRepositoryInterface::class);
        $repository->expects(self::once())->method('reserve')->willReturn([
            ['id' => 17, 'event_name' => 'AddressCreated', 'event_version' => 1, 'payload' => '{'],
            ['id' => 18, 'event_name' => 'AddressCreated', 'event_version' => 1, 'payload' => '{"id":"addr-18"}'],
        ]);
        $repository->expects(self::once())->method('markDispatchFailure')->with(17, 'invalid_outbox_payload');
        $repository->expects(self::once())->method('markPublished')->with(18);

        $sent = [];
        $sender = static function (string $url, array $data) use (&$sent): bool {
            $sent[] = $data;

            return true;
        };

        self::assertSame(2, (new AddressOutboxDrainerService($repository, $sender))->drain('http://example.test'));
        self::assertSame([[
            'name' => 'AddressCreated',
            'version' => 1,
            'payload' => ['id' => 'addr-18'],
        ]], $sent);
    }

    public function testReservationReturnsOnlyRowsActuallyClaimed(): void
    {
        $entityManager = \Tests\Support\TestDatabase::createInMemoryEntityManager([\App\Addressing\Entity\AddressOutboxEntity::class]);
        $repository = new \App\Addressing\Repository\AddressDoctrineOutboxDispatchRepository($entityManager);
        $entityManager->persist((new \App\Addressing\Entity\AddressOutboxEntity())
            ->setEventName('AddressCreated')
            ->setEventVersion(1)
            ->setPayload('{"id":"addr-lost"}')
            ->setCreatedAt(new \DateTimeImmutable()));
        $entityManager->flush();

        // Simulate a lost conditional write at the database boundary without timing races.
        $entityManager->getConnection()->executeStatement(
            "CREATE TRIGGER ignore_lost_claim BEFORE UPDATE OF locked_at ON address_outbox WHEN NEW.locked_by = 'worker-lost' BEGIN SELECT RAISE(IGNORE); END",
        );

        self::assertSame([], $repository->reserve('worker-lost', 1));
        $winner = $repository->reserve('worker-winner', 1);
        self::assertCount(1, $winner);
        self::assertSame([], $repository->reserve('worker-other', 1));
    }

    public function testClaimBatchRollsBackAndCompletionIsConditional(): void
    {
        $entityManager = \Tests\Support\TestDatabase::createInMemoryEntityManager([\App\Addressing\Entity\AddressOutboxEntity::class]);
        $repository = new \App\Addressing\Repository\AddressDoctrineOutboxDispatchRepository($entityManager);
        foreach (['first', 'second'] as $id) {
            $entityManager->persist((new \App\Addressing\Entity\AddressOutboxEntity())
                ->setEventName('AddressCreated')
                ->setEventVersion(1)
                ->setPayload(json_encode(['id' => $id], JSON_THROW_ON_ERROR))
                ->setCreatedAt(new \DateTimeImmutable()));
        }
        $entityManager->flush();
        $connection = $entityManager->getConnection();
        $connection->executeStatement(
            "CREATE TRIGGER abort_second_claim BEFORE UPDATE OF locked_at ON address_outbox WHEN OLD.id = 2 AND NEW.locked_by = 'worker-fail' BEGIN SELECT RAISE(ABORT, 'claim_failed'); END",
        );

        try {
            $repository->reserve('worker-fail', 2);
            self::fail('Expected database claim failure.');
        } catch (\Doctrine\DBAL\Exception $exception) {
            self::assertStringContainsString('claim_failed', $exception->getMessage());
        }

        self::assertFalse($connection->isTransactionActive());
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM address_outbox WHERE locked_at IS NOT NULL'));

        $repository->markPublished(1);
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM address_outbox WHERE published_at IS NOT NULL'));

        self::assertCount(2, $repository->reserve('worker-retry', 2));
        $repository->markPublished(1);
        $repository->markPublished(1);
        $repository->markDispatchFailure(1, 'late failure');
        self::assertSame(1, (int) $connection->fetchOne('SELECT published_attempt FROM address_outbox WHERE id = 1'));
        self::assertNull($connection->fetchOne('SELECT locked_at FROM address_outbox WHERE id = 1'));
        $repository->markDispatchFailure(2, 'retry');
        self::assertCount(1, $repository->reserve('worker-next', 2));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'malformed JSON' => ['{'];
        yield 'null' => ['null'];
        yield 'list' => ['["value"]'];
        yield 'empty list' => ['[]'];
        yield 'numeric key' => ['{"0":"value","id":"addr-17"}'];
    }
}
