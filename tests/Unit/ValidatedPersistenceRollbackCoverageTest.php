<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\Entity\AddressOutboxEntity;
use App\Addressing\Repository\AddressDoctrineOutboxDispatchRepository;
use App\Addressing\Repository\AddressDoctrineValidatedPersistenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class ValidatedPersistenceRollbackCoverageTest extends TestCase
{
    public function testRollbackCoversInactiveAndActiveTransactions(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([AddressEntity::class]);
        $repository = new AddressDoctrineValidatedPersistenceRepository($entityManager);

        $repository->rollbackIfActive();
        self::assertFalse($entityManager->getConnection()->isTransactionActive());

        $repository->beginTransaction();
        self::assertTrue($entityManager->getConnection()->isTransactionActive());

        $repository->rollbackIfActive();
        self::assertFalse($entityManager->getConnection()->isTransactionActive());
    }

    public function testOutboxReserveRollsBackAndRethrowsRepositoryFailure(): void
    {
        $failure = new \RuntimeException('reserve_failed');
        $objectRepository = $this->createMock(EntityRepository::class);
        $objectRepository->method('findBy')->willThrowException($failure);

        $connection = TestDatabase::createInMemoryEntityManager([AddressOutboxEntity::class])->getConnection();
        $connection->beginTransaction();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('beginTransaction');
        $entityManager->expects(self::once())
            ->method('getRepository')
            ->with(AddressOutboxEntity::class)
            ->willReturn($objectRepository);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->expects(self::once())->method('rollback');

        try {
            (new AddressDoctrineOutboxDispatchRepository($entityManager))->reserve('worker-fail', 1);
            self::fail('Expected reserve failure to be rethrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }
}
