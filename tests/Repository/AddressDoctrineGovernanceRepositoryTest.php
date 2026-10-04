<?php

declare(strict_types=1);

namespace Tests\Repository;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\Factory\AddressEntityMapper;
use App\Addressing\Repository\AddressDoctrineGovernanceRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class AddressDoctrineGovernanceRepositoryTest extends TestCase
{
    public function testSummaryPreservesGovernanceClusterContract(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([AddressEntity::class]);
        $repository = new AddressDoctrineGovernanceRepository($entityManager, new AddressEntityMapper());

        foreach ([
            ['target', 'canonical', null, null, null, null],
            ['current', 'alias', null, null, 'target', null],
            ['duplicate-child', 'duplicate', 'current', null, null, null],
            ['superseded-child', 'superseded', null, 'current', null, null],
            ['alias-child', 'alias', null, null, 'current', null],
            ['conflict-peer', 'conflict', null, null, null, 'current'],
        ] as [$id, $status, $duplicateOf, $supersededBy, $aliasOf, $conflictWith]) {
            $entityManager->persist((new AddressEntity())
                ->setId($id)
                ->setOwnerId('owner-1')
                ->setLine1('123 Main St')
                ->setCity('Houston')
                ->setCountryCode('US')
                ->setValidationStatus('pending')
                ->setGovernanceStatus($status)
                ->setDuplicateOfId($duplicateOf)
                ->setSupersededById($supersededBy)
                ->setAliasOfId($aliasOf)
                ->setConflictWithId($conflictWith)
                ->setCreatedAt(new \DateTimeImmutable('2026-01-01T00:00:00+00:00')));
        }
        $entityManager->flush();

        $summary = $repository->summarizeGovernanceCluster('current', 'owner-1', null);

        self::assertSame('alias', $summary['governanceStatus']);
        self::assertSame('target', $summary['primaryLinkId']);
        self::assertSame(1, $summary['duplicateChildren']);
        self::assertSame(1, $summary['supersededChildren']);
        self::assertSame(1, $summary['aliasChildren']);
        self::assertSame(1, $summary['conflictPeers']);
        self::assertSame(4, $summary['inboundLinkedTotal']);
        self::assertSame(6, $summary['clusterSize']);

        $relatedIds = $summary['relatedAddressIds'];
        sort($relatedIds);
        self::assertSame(['alias-child', 'conflict-peer', 'duplicate-child', 'superseded-child', 'target'], $relatedIds);

        self::assertSame(0, $repository->summarizeGovernanceCluster('current', 'owner-2', null)['clusterSize']);
    }
}
