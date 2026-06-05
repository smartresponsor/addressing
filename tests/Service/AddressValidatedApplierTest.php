<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Service;

use App\Contract\Message\AddressValidated;
use App\Entity\AddressEntity;
use App\Entity\AddressEvidenceSnapshotEntity;
use App\Entity\AddressOutboxEntity;
use App\Service\Application\AddressValidatedApplierService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class AddressValidatedApplierTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private AddressValidatedApplierService $applier;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::createInMemoryEntityManager([
            AddressEntity::class,
            AddressEvidenceSnapshotEntity::class,
            AddressOutboxEntity::class,
        ]);
        $this->applier = new AddressValidatedApplierService($this->entityManager);
    }

    public function testApplyWorksOnSqliteWithoutPgsqlLockSyntax(): void
    {
        $this->insertAddress('addr-1', 'owner-1', 'vendor-1');

        $validated = AddressValidated::fromArray([
            'line1Norm' => 'main st',
            'cityNorm' => 'houston',
            'validationProvider' => 'unit',
            'sourceSystem' => 'validator-suite',
            'sourceType' => 'validator',
            'sourceReference' => 'run-1',
            'normalizationVersion' => 'canon-w08',
            'rawInput' => ['line1' => '123 Main St', 'city' => 'Houston'],
            'normalizedSnapshot' => ['line1Norm' => 'main st', 'cityNorm' => 'houston'],
            'providerDigest' => 'digest-1',
            'governanceStatus' => 'superseded',
            'supersededById' => 'addr-2',
            'revalidationDueAt' => '2025-03-01T00:00:00+00:00',
            'revalidationPolicy' => 'quarterly',
            'lastValidationProvider' => 'unit',
            'lastValidationStatus' => 'validated',
            'lastValidationScore' => 87,
        ]);

        $this->applier->apply('addr-1', $validated, 'owner-1', 'vendor-1');

        $address = $this->entityManager->find(AddressEntity::class, 'addr-1');
        self::assertInstanceOf(AddressEntity::class, $address);
        self::assertSame('validated', $address->getValidationStatus());
        self::assertSame('main st', $address->getLine1Norm());
        self::assertSame('validator-suite', $address->getSourceSystem());
        self::assertSame('validator', $address->getSourceType());
        self::assertSame('digest-1', $address->getProviderDigest());
        self::assertSame('superseded', $address->getGovernanceStatus());
        self::assertSame('addr-2', $address->getSupersededById());
        self::assertNotNull($address->getRevalidationDueAt());
        self::assertSame('quarterly', $address->getRevalidationPolicy());
        self::assertSame('unit', $address->getLastValidationProvider());
        self::assertSame('validated', $address->getLastValidationStatus());
        self::assertSame(87, $address->getLastValidationScore());

        /** @var list<AddressEvidenceSnapshotEntity> $snapshots */
        $snapshots = $this->entityManager->getRepository(AddressEvidenceSnapshotEntity::class)->findBy(
            ['address' => $address],
            ['createdAt' => 'DESC', 'id' => 'DESC'],
            1,
        );
        self::assertCount(1, $snapshots);
        $snapshot = $snapshots[0];
        self::assertSame('validator-suite', $snapshot->getSourceSystem());
        self::assertSame('validator', $snapshot->getSourceType());
        self::assertSame('run-1', $snapshot->getSourceReference());
        self::assertSame('unit', $snapshot->getValidatedBy());
        self::assertSame('validated', $snapshot->getValidationStatus());
        self::assertSame(87, $snapshot->getValidationScore());
        self::assertSame('digest-1', $snapshot->getProviderDigest());

        /** @var list<AddressOutboxEntity> $outboxRows */
        $outboxRows = $this->entityManager->getRepository(AddressOutboxEntity::class)->findBy([], ['id' => 'DESC'], 1);
        self::assertCount(1, $outboxRows);
        $outbox = $outboxRows[0];
        self::assertSame('AddressValidatedApplied', $outbox->getEventName());
        self::assertSame(1, $outbox->getEventVersion());
        $payload = json_decode($outbox->getPayload(), true);
        self::assertIsArray($payload);
        self::assertSame('AddressValidatedApplied', $payload['eventName'] ?? null);
        self::assertSame('address-outbox.v1', $payload['schemaVersion'] ?? null);
        self::assertSame(1, $payload['eventVersion'] ?? null);
    }

    public function testApplyRejectsWrongTenantScope(): void
    {
        $this->insertAddress('addr-2', 'owner-A', 'vendor-A');

        $validated = AddressValidated::fromArray([
            'line1Norm' => 'changed',
            'validationProvider' => 'unit',
            'sourceSystem' => 'validator-suite',
            'sourceType' => 'validator',
            'sourceReference' => 'run-1',
            'normalizationVersion' => 'canon-w08',
            'rawInput' => ['line1' => '123 Main St', 'city' => 'Houston'],
            'normalizedSnapshot' => ['line1Norm' => 'main st', 'cityNorm' => 'houston'],
            'providerDigest' => 'digest-1',
            'governanceStatus' => 'superseded',
            'supersededById' => 'addr-2',
            'revalidationDueAt' => '2025-03-01T00:00:00+00:00',
            'revalidationPolicy' => 'quarterly',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not_found');
        $this->applier->apply('addr-2', $validated, 'owner-B', 'vendor-B');
    }

    private function insertAddress(string $id, string $ownerId, string $vendorId): void
    {
        $address = (new AddressEntity())
            ->setId($id)
            ->setOwnerId($ownerId)
            ->setVendorId($vendorId)
            ->setLine1('123 Main St')
            ->setCity('Houston')
            ->setCountryCode('US')
            ->setValidationStatus('pending')
            ->setCreatedAt(new \DateTimeImmutable('2025-01-01 00:00:00+00:00'));

        $this->entityManager->persist($address);
        $this->entityManager->flush();
    }
}
