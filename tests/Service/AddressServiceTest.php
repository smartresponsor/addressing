<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Service;

use App\Entity\Record\AddressData;
use App\Repository\Persistence\AddressRepository;
use App\Service\Application\AddressService;
use PHPUnit\Framework\TestCase;

final class AddressServiceTest extends TestCase
{
    private \PDO $pdo;
    private AddressRepository $repo;
    private AddressService $service;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec($this->schemaSql());
        $this->repo = new AddressRepository($this->pdo);
        $this->service = new AddressService($this->repo);
    }

    public function testCreateStoresAddress(): void
    {
        $address = $this->makeAddress('addr-1');
        $this->service->create($address);

        $found = $this->repo->get('addr-1', $address->ownerId(), $address->vendorId());
        static::assertNotNull($found);
        static::assertSame('123 Main St', $found->line1());
        static::assertSame('manual', $found->sourceType());
        static::assertSame('unit-test', $found->sourceSystem());
        static::assertSame('sha256:addr-1', $found->providerDigest());
    }

    public function testUpdateChangesAddress(): void
    {
        $address = $this->makeAddress('addr-2');
        $this->service->create($address);

        $updated = $this->makeAddress('addr-2', line1: '456 Broad St', updatedAt: (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'));
        $this->service->update($updated);

        $found = $this->repo->get('addr-2', $updated->ownerId(), $updated->vendorId());
        static::assertNotNull($found);
        static::assertSame('456 Broad St', $found->line1());
    }

    public function testSearchReturnsMatchingRows(): void
    {
        $this->service->create($this->makeAddress('addr-3'));
        $this->service->create($this->makeAddress('addr-4', line1: '500 Elm St'));

        $result = $this->service->search('owner-1', 'vendor-1', null, 'Main', 10, null);
        static::assertCount(1, $result['items']);
        static::assertSame('123 Main St', $result['items'][0]->line1());
    }

    public function testSearchSupportsOperationalFilters(): void
    {
        $this->service->create($this->makeAddress('addr-filter-1'));
        $withGovernance = new AddressData(
            'addr-filter-2',
            'owner-1',
            'vendor-1',
            '500 Elm St',
            null,
            'Houston',
            'TX',
            '77002',
            'US',
            '500elmst',
            'houston',
            'tx',
            '77002',
            null,
            null,
            null,
            'validated',
            'validator-suite',
            '2025-01-03 00:00:00+00:00',
            '500elmst|houston|tx|77002|US|owner-1|vendor-1',
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            null,
            null,
            'fingerprint-filter',
            ['provider' => 'validator-suite'],
            ['quality' => 93],
            true,
            'premise',
            93,
            'validator-suite',
            'validator',
            'run-filter',
            'canon-w11',
            ['line1' => '500 Elm St'],
            ['line1Norm' => '500elmst'],
            'digest-filter',
            'duplicate',
            'addr-master',
            null,
            null,
            null,
            '2025-01-15 00:00:00+00:00',
            'monthly',
            'validator-suite',
            'validated',
            93
        );
        $this->service->create($withGovernance);

        $result = $this->service->search('owner-1', 'vendor-1', null, null, 10, null, [
            'sourceType' => 'validator',
            'governanceStatus' => 'duplicate',
            'hasEvidence' => true,
            'revalidationDueBefore' => '2025-01-31 00:00:00+00:00',
        ]);

        static::assertCount(1, $result['items']);
        static::assertSame('addr-filter-2', $result['items'][0]->id());
        static::assertSame('duplicate', $result['items'][0]->governanceStatus());
        static::assertSame('monthly', $result['items'][0]->revalidationPolicy());
    }

    public function testEvidenceHistoryTracksCreateAndUpdate(): void
    {
        $address = $this->makeAddress('addr-history-1');
        $this->service->create($address);

        $updated = new AddressData(
            'addr-history-1',
            'owner-1',
            'vendor-1',
            '123 Main St',
            null,
            'Houston',
            'TX',
            '77002',
            'US',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'validated',
            'validator-suite',
            '2025-02-02 00:00:00+00:00',
            null,
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            null,
            'fingerprint-history-1',
            ['provider' => 'validator-suite', 'issues' => ['postal']],
            ['quality' => 98],
            true,
            'premise',
            98,
            'validator-suite',
            'validator',
            'run-history-1',
            'canon-w15',
            ['line1' => '123 Main St'],
            ['line1Norm' => '123mainst'],
            'digest-history-1',
            'canonical',
            null,
            null,
            null,
            null,
            '2025-06-01 00:00:00+00:00',
            'quarterly',
            'validator-suite',
            'validated',
            98
        );
        $this->service->update($updated);

        $latest = $this->service->getLatestEvidenceSnapshot('addr-history-1', 'owner-1', 'vendor-1');
        self::assertNotNull($latest);
        self::assertSame('run-history-1', $latest->sourceReference());
        self::assertSame('validated', $latest->validationStatus());
        self::assertSame(98, $latest->validationScore());

        $history = $this->service->evidenceHistory('addr-history-1', 'owner-1', 'vendor-1', 10, null);
        self::assertCount(2, $history['items']);
        self::assertSame('run-history-1', $history['items'][0]->sourceReference());
        self::assertSame('fixture:addr-history-1', $history['items'][1]->sourceReference());
        self::assertNull($history['nextCursor']);
    }

    public function testEvidenceHistorySummaryAggregatesSnapshots(): void
    {
        $this->service->create($this->makeAddress('addr-history-summary'));

        $updated = new AddressData(
            'addr-history-summary',
            'owner-1',
            'vendor-1',
            '123 Main St',
            null,
            'Houston',
            'TX',
            '77002',
            'US',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'validated',
            'validator-suite',
            '2025-02-02 00:00:00+00:00',
            null,
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            null,
            'fingerprint-history-summary',
            ['provider' => 'validator-suite'],
            ['quality' => 99],
            true,
            'premise',
            99,
            'validator-suite',
            'validator',
            'run-history-summary',
            'canon-w21',
            ['line1' => '123 Main St'],
            ['line1Norm' => '123mainst'],
            'digest-history-summary',
            'canonical',
            null,
            null,
            null,
            null,
            '2025-06-01 00:00:00+00:00',
            'quarterly',
            'validator-suite',
            'validated',
            99
        );
        $this->service->update($updated);

        $summary = $this->service->evidenceHistorySummary('addr-history-summary', 'owner-1', 'vendor-1');
        self::assertSame(2, $summary['totalSnapshots']);
        self::assertSame(1, $summary['statusPending']);
        self::assertSame(1, $summary['statusValidated']);
        self::assertSame(0, $summary['statusRejected']);
        self::assertSame(2, $summary['distinctProviders']);
        self::assertSame('2025-02-02 00:00:00+00:00', $summary['latestValidatedAt']);
        self::assertNotNull($summary['latestCreatedAt']);
    }

    public function testSearchSupportsOperationalQueues(): void
    {
        $this->service->create($this->makeAddress('addr-queue-evidence', sourceType: 'manual', rawInputSnapshot: null, normalizedSnapshot: null, providerDigest: null, sourceReference: null));
        $this->service->create($this->makeAddress('addr-queue-uncertain', validationStatus: 'uncertain', lastValidationStatus: 'uncertain'));
        $this->service->create($this->makeAddress('addr-queue-conflict', governanceStatus: 'conflict', conflictWithId: 'addr-master-conflict'));
        $this->service->create($this->makeAddress('addr-queue-duplicate', governanceStatus: 'duplicate', duplicateOfId: 'addr-master-duplicate'));
        $this->service->create($this->makeAddress('addr-queue-revalidation', revalidationDueAt: '2025-01-01 00:00:00+00:00'));
        $this->service->create($this->makeAddress('addr-queue-stale', normalizationVersion: 'canon-old'));

        $due = $this->service->search('owner-1', 'vendor-1', null, null, 20, null, ['queue' => 'dueForRevalidation']);
        self::assertSame('addr-queue-revalidation', $due['items'][0]->id());

        $missingEvidence = $this->service->search('owner-1', 'vendor-1', null, null, 20, null, ['queue' => 'evidenceMissing']);
        self::assertSame('addr-queue-evidence', $missingEvidence['items'][0]->id());

        $uncertain = $this->service->search('owner-1', 'vendor-1', null, null, 20, null, ['queue' => 'uncertainValidation']);
        self::assertSame('addr-queue-uncertain', $uncertain['items'][0]->id());

        $conflict = $this->service->search('owner-1', 'vendor-1', null, null, 20, null, ['queue' => 'conflictReview']);
        self::assertSame('addr-queue-conflict', $conflict['items'][0]->id());

        $duplicate = $this->service->search('owner-1', 'vendor-1', null, null, 20, null, ['queue' => 'duplicateReview']);
        self::assertSame('addr-queue-duplicate', $duplicate['items'][0]->id());

        $stale = $this->service->search('owner-1', 'vendor-1', null, null, 20, null, ['queue' => 'staleNormalizationVersion', 'expectedNormalizationVersion' => 'canon-w15']);
        self::assertSame('addr-queue-stale', $stale['items'][0]->id());
    }

    public function testGovernanceClusterSummaryAggregatesLinkedRecords(): void
    {
        $this->service->create($this->makeAddress('addr-cluster-root'));
        $this->service->create($this->makeAddress('addr-cluster-dup', dedupeKey: 'dedupe-dup'));
        $this->service->create($this->makeAddress('addr-cluster-sup', dedupeKey: 'dedupe-sup'));
        $this->service->create($this->makeAddress('addr-cluster-alias', dedupeKey: 'dedupe-alias'));
        $this->service->create($this->makeAddress('addr-cluster-conflict', dedupeKey: 'dedupe-conflict'));

        $this->service->patchOperational('addr-cluster-dup', 'owner-1', 'vendor-1', [
            'governanceStatus' => 'duplicate',
            'duplicateOfId' => 'addr-cluster-root',
        ]);
        $this->service->patchOperational('addr-cluster-sup', 'owner-1', 'vendor-1', [
            'governanceStatus' => 'superseded',
            'supersededById' => 'addr-cluster-root',
        ]);
        $this->service->patchOperational('addr-cluster-alias', 'owner-1', 'vendor-1', [
            'governanceStatus' => 'alias',
            'aliasOfId' => 'addr-cluster-root',
        ]);
        $this->service->patchOperational('addr-cluster-conflict', 'owner-1', 'vendor-1', [
            'governanceStatus' => 'conflict',
            'conflictWithId' => 'addr-cluster-root',
        ]);

        $summary = $this->service->governanceClusterSummary('addr-cluster-root', 'owner-1', 'vendor-1');
        self::assertSame('addr-cluster-root', $summary['addressId']);
        self::assertSame('canonical', $summary['governanceStatus']);
        self::assertNull($summary['primaryLinkId']);
        self::assertFalse($summary['linkedToAnother']);
        self::assertSame(1, $summary['duplicateChildren']);
        self::assertSame(1, $summary['supersededChildren']);
        self::assertSame(1, $summary['aliasChildren']);
        self::assertSame(1, $summary['conflictPeers']);
        self::assertSame(4, $summary['inboundLinkedTotal']);
        self::assertSame(5, $summary['clusterSize']);
        self::assertSame([
            'addr-cluster-alias',
            'addr-cluster-conflict',
            'addr-cluster-dup',
            'addr-cluster-sup',
        ], $summary['relatedAddressIds']);
    }

    public function testOperationalQueueSummaryAggregatesCurrentSlice(): void
    {
        $this->service->create($this->makeAddress('addr-sum-evidence', sourceType: 'manual', rawInputSnapshot: null, normalizedSnapshot: null, providerDigest: null, sourceReference: null));
        $this->service->create($this->makeAddress('addr-sum-uncertain', validationStatus: 'uncertain', lastValidationStatus: 'uncertain'));
        $this->service->create($this->makeAddress('addr-sum-conflict', governanceStatus: 'conflict', conflictWithId: 'addr-master-conflict'));
        $this->service->create($this->makeAddress('addr-sum-duplicate', governanceStatus: 'duplicate', duplicateOfId: 'addr-master-duplicate'));
        $this->service->create($this->makeAddress('addr-sum-revalidation', revalidationDueAt: '2025-01-01 00:00:00+00:00'));
        $this->service->create($this->makeAddress('addr-sum-stale', normalizationVersion: 'canon-old'));

        $summary = $this->service->operationalQueueSummary('owner-1', 'vendor-1', null, null, [
            'expectedNormalizationVersion' => 'canon-w15',
        ]);

        self::assertSame(6, $summary['total']);
        self::assertSame(1, $summary['dueForRevalidation']);
        self::assertSame(1, $summary['evidenceMissing']);
        self::assertSame(1, $summary['uncertainValidation']);
        self::assertSame(1, $summary['conflictReview']);
        self::assertSame(1, $summary['duplicateReview']);
        self::assertSame(1, $summary['staleNormalizationVersion']);
    }

    public function testCountryPortfolioSummaryAggregatesByCountry(): void
    {
        $this->service->create($this->makeAddress('addr-country-us-1'));
        $this->service->create($this->makeAddress('addr-country-us-2', governanceStatus: 'duplicate', duplicateOfId: 'addr-country-us-1', validationStatus: 'uncertain', lastValidationStatus: 'uncertain'));
        $this->service->create($this->makeAddress('addr-country-ca-1', countryCode: 'CA', sourceType: 'partner', rawInputSnapshot: null, normalizedSnapshot: null, providerDigest: null, sourceReference: null, revalidationDueAt: '2025-01-01 00:00:00+00:00'));

        $summary = $this->service->countryPortfolioSummary('owner-1', 'vendor-1');

        self::assertCount(2, $summary);
        self::assertSame('US', $summary[0]['countryCode']);
        self::assertSame(2, $summary[0]['total']);
        self::assertSame(1, $summary[0]['canonical']);
        self::assertSame(1, $summary[0]['duplicate']);
        self::assertSame(1, $summary[0]['uncertainValidation']);
        self::assertSame('CA', $summary[1]['countryCode']);
        self::assertSame(1, $summary[1]['total']);
        self::assertSame(1, $summary[1]['dueForRevalidation']);
        self::assertSame(1, $summary[1]['evidenceMissing']);
    }

    public function testSourcePortfolioSummaryAggregatesBySource(): void
    {
        $this->service->create($this->makeAddress('addr-source-1', sourceSystem: 'unit-test', sourceType: 'manual'));
        $this->service->create($this->makeAddress('addr-source-2', sourceSystem: 'validator-suite', sourceType: 'validator', governanceStatus: 'duplicate', duplicateOfId: 'addr-source-1', validationStatus: 'uncertain', lastValidationStatus: 'uncertain'));
        $this->service->create($this->makeAddress('addr-source-3', sourceSystem: 'validator-suite', sourceType: 'validator', countryCode: 'CA', rawInputSnapshot: null, normalizedSnapshot: null, providerDigest: null, sourceReference: null, revalidationDueAt: '2025-01-01 00:00:00+00:00'));

        $summary = $this->service->sourcePortfolioSummary('owner-1', 'vendor-1', null, null);

        self::assertCount(2, $summary);
        self::assertSame('validator-suite', $summary[0]['sourceSystem']);
        self::assertSame('validator', $summary[0]['sourceType']);
        self::assertSame(2, $summary[0]['total']);
        self::assertSame(1, $summary[0]['duplicate']);
        self::assertSame(1, $summary[0]['uncertainValidation']);
        self::assertSame(1, $summary[0]['dueForRevalidation']);
        self::assertSame(1, $summary[0]['evidenceMissing']);
        self::assertSame('unit-test', $summary[1]['sourceSystem']);
        self::assertSame('manual', $summary[1]['sourceType']);
        self::assertSame(1, $summary[1]['total']);
    }

    public function testValidationPortfolioSummaryAggregatesByProviderAndStatus(): void
    {
        $this->service->create($this->makeAddress('addr-validation-1', validationProvider: 'provider-a', validationStatus: 'validated', lastValidationProvider: 'provider-a', lastValidationStatus: 'validated'));
        $this->service->create($this->makeAddress('addr-validation-2', validationProvider: 'provider-a', validationStatus: 'uncertain', lastValidationProvider: 'provider-a', lastValidationStatus: 'uncertain', governanceStatus: 'duplicate', duplicateOfId: 'addr-validation-1'));
        $this->service->create($this->makeAddress('addr-validation-3', validationProvider: 'provider-b', validationStatus: 'pending', lastValidationProvider: 'provider-b', lastValidationStatus: 'rejected', rawInputSnapshot: null, normalizedSnapshot: null, providerDigest: null, sourceReference: null, revalidationDueAt: '2025-01-01 00:00:00+00:00'));

        $summary = $this->service->validationPortfolioSummary('owner-1', 'vendor-1', null, null);

        self::assertCount(3, $summary);
        self::assertSame('provider-a', $summary[0]['validationProvider']);
        self::assertSame('uncertain', $summary[0]['validationStatus']);
        self::assertSame(1, $summary[0]['duplicate']);
        self::assertSame(1, $summary[0]['uncertainValidation']);
        self::assertSame('provider-a', $summary[1]['validationProvider']);
        self::assertSame('validated', $summary[1]['validationStatus']);
        self::assertSame(1, $summary[1]['canonical']);
        self::assertSame('provider-b', $summary[2]['validationProvider']);
        self::assertSame('rejected', $summary[2]['validationStatus']);
        self::assertSame(1, $summary[2]['dueForRevalidation']);
        self::assertSame(1, $summary[2]['evidenceMissing']);
    }

    public function testNormalizationPortfolioSummaryAggregatesByVersionAndStatus(): void
    {
        $this->service->create($this->makeAddress('addr-normalization-1', normalizationVersion: 'canon-w15', validationStatus: 'validated', lastValidationStatus: 'validated'));
        $this->service->create($this->makeAddress('addr-normalization-2', normalizationVersion: 'canon-w15', validationStatus: 'uncertain', lastValidationStatus: 'uncertain', governanceStatus: 'duplicate', duplicateOfId: 'addr-normalization-1'));
        $this->service->create($this->makeAddress('addr-normalization-3', normalizationVersion: 'canon-old', validationStatus: 'rejected', lastValidationStatus: 'rejected', rawInputSnapshot: null, normalizedSnapshot: null, providerDigest: null, sourceReference: null, revalidationDueAt: '2025-01-01 00:00:00+00:00'));

        $summary = $this->service->normalizationPortfolioSummary('owner-1', 'vendor-1', null, null, [
            'expectedNormalizationVersion' => 'canon-w15',
        ]);

        self::assertCount(3, $summary);
        self::assertSame('canon-w15', $summary[0]['normalizationVersion']);
        self::assertSame('uncertain', $summary[0]['validationStatus']);
        self::assertSame(1, $summary[0]['duplicate']);
        self::assertSame(1, $summary[0]['uncertainValidation']);
        self::assertSame(0, $summary[0]['staleNormalization']);
        self::assertSame('canon-old', $summary[2]['normalizationVersion']);
        self::assertSame('rejected', $summary[2]['validationStatus']);
        self::assertSame(1, $summary[2]['dueForRevalidation']);
        self::assertSame(1, $summary[2]['evidenceMissing']);
        self::assertSame(1, $summary[2]['staleNormalization']);
    }

    public function testPatchOperationalRejectsMissingGovernanceLink(): void
    {
        $this->service->create($this->makeAddress('addr-policy-1'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('requires a non-self link id');

        $this->service->patchOperational('addr-policy-1', 'owner-1', 'vendor-1', [
            'governanceStatus' => 'duplicate',
        ]);
    }

    public function testPatchOperationalRejectsDirectTransitionToCanonicalFromSuperseded(): void
    {
        $this->service->create($this->makeAddress('addr-policy-2'));
        $this->service->create($this->makeAddress('addr-policy-master'));

        $ok = $this->service->patchOperational('addr-policy-2', 'owner-1', 'vendor-1', [
            'governanceStatus' => 'superseded',
            'supersededById' => 'addr-policy-master',
        ]);
        self::assertTrue($ok);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid governance transition from "superseded" to "canonical"');

        $this->service->patchOperational('addr-policy-2', 'owner-1', 'vendor-1', [
            'governanceStatus' => 'canonical',
        ]);
    }

    public function testPatchOperationalRejectsMissingGovernanceTargetInTenantScope(): void
    {
        $this->service->create($this->makeAddress('addr-policy-3'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('was not found in the current tenant scope');

        $this->service->patchOperational('addr-policy-3', 'owner-1', 'vendor-1', [
            'governanceStatus' => 'alias',
            'aliasOfId' => 'missing-master',
        ]);
    }

    public function testPatchOperationalUpdatesGovernanceAndRevalidation(): void
    {
        $this->service->create($this->makeAddress('addr-master-1'));
        $this->service->create($this->makeAddress('addr-patch-1'));

        $ok = $this->service->patchOperational('addr-patch-1', 'owner-1', 'vendor-1', [
            'governanceStatus' => 'superseded',
            'supersededById' => 'addr-master-1',
            'revalidationDueAt' => '2025-05-01 00:00:00+00:00',
            'revalidationPolicy' => 'quarterly',
            'lastValidationProvider' => 'validator-suite',
            'lastValidationStatus' => 'validated',
            'lastValidationScore' => 97,
        ]);

        self::assertTrue($ok);
        $saved = $this->service->get('addr-patch-1', 'owner-1', 'vendor-1');
        self::assertNotNull($saved);
        self::assertSame('superseded', $saved->governanceStatus());
        self::assertSame('addr-master-1', $saved->supersededById());
        self::assertSame('2025-05-01 00:00:00+00:00', $saved->revalidationDueAt());
        self::assertSame('quarterly', $saved->revalidationPolicy());
        self::assertSame('validator-suite', $saved->lastValidationProvider());
        self::assertSame('validated', $saved->lastValidationStatus());
        self::assertSame(97, $saved->lastValidationScore());

        $row = $this->pdo->query('SELECT event_name, payload FROM address_outbox ORDER BY id DESC LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('AddressOperationalPatched', $row['event_name']);
        $payload = json_decode((string) $row['payload'], true);
        self::assertSame('superseded', $payload['governanceStatus'] ?? null);
        self::assertSame('addr-master-1', $payload['governanceLinkId'] ?? null);
        self::assertSame('quarterly', $payload['revalidationPolicy'] ?? null);
        self::assertSame('AddressOperationalPatched', $payload['eventName'] ?? null);
        self::assertSame('address-outbox.v1', $payload['schemaVersion'] ?? null);
        self::assertSame(1, $payload['eventVersion'] ?? null);
    }

    public function testDedupeFindsExistingAddress(): void
    {
        $this->service->create($this->makeAddress('addr-5', dedupeKey: 'dedupe-1'));

        $found = $this->service->dedupe('dedupe-1');
        static::assertNotNull($found);
        static::assertSame('addr-5', $found->id());
    }

    public function testOutboxEventRecordedOnCreate(): void
    {
        $this->service->create($this->makeAddress('addr-6'));

        $row = $this->pdo->query('SELECT event_name, event_version, payload FROM address_outbox ORDER BY id ASC')
            ->fetch(\PDO::FETCH_ASSOC);

        static::assertNotFalse($row);
        static::assertSame('AddressCreated', $row['event_name']);
        static::assertSame(1, (int) $row['event_version']);
        $payload = json_decode((string) $row['payload'], true);
        static::assertSame('addr-6', $payload['id'] ?? null);
        static::assertSame('AddressCreated', $payload['eventName'] ?? null);
        static::assertSame('address-outbox.v1', $payload['schemaVersion'] ?? null);
        static::assertSame(1, $payload['eventVersion'] ?? null);
    }

    public function testUpdateMissingRowDoesNotWriteOutbox(): void
    {
        $this->service->update($this->makeAddress('missing-1'));

        static::assertSame(0, $this->outboxCount());
    }

    public function testDeleteMissingRowDoesNotWriteOutbox(): void
    {
        $this->repo->delete('missing-2', 'owner-1', 'vendor-1');

        static::assertSame(0, $this->outboxCount());
    }

    public function testRevalidationFieldsPersistOnCreateAndUpdate(): void
    {
        $address = $this->makeAddress('addr-reval');
        $this->service->create($address);

        $updated = new AddressData(
            'addr-reval',
            'owner-1',
            'vendor-1',
            '123 Main St',
            null,
            'Houston',
            'TX',
            '77002',
            'US',
            '123mainst',
            'houston',
            'tx',
            '77002',
            null,
            null,
            null,
            'validated',
            'validator-suite',
            '2025-01-02 00:00:00+00:00',
            '123mainst|houston|tx|77002|US|owner-1|vendor-1',
            $address->createdAt(),
            '2025-01-03 00:00:00+00:00',
            null,
            'fingerprint-reval',
            ['provider' => 'validator-suite'],
            ['quality' => 91],
            true,
            'premise',
            91,
            'validator-suite',
            'validator',
            'run-reval',
            'canon-w08',
            ['line1' => '123 Main St'],
            ['line1Norm' => '123mainst'],
            'digest-reval',
            'canonical',
            null,
            null,
            null,
            null,
            '2025-04-01 00:00:00+00:00',
            'monthly',
            'validator-suite',
            'validated',
            91
        );

        $this->service->update($updated);

        $saved = $this->service->get('addr-reval', 'owner-1', 'vendor-1');
        self::assertNotNull($saved);
        self::assertSame('2025-04-01 00:00:00+00:00', $saved->revalidationDueAt());
        self::assertSame('monthly', $saved->revalidationPolicy());
        self::assertSame('validator-suite', $saved->lastValidationProvider());
        self::assertSame('validated', $saved->lastValidationStatus());
        self::assertSame(91, $saved->lastValidationScore());
    }

    public function testGovernanceLinksPersistThroughCreateAndUpdate(): void
    {
        $address = $this->makeAddress('addr-gov');
        $this->service->create($address);

        $duplicate = new AddressData(
            'addr-gov',
            'owner-1',
            'vendor-1',
            '123 Main St',
            null,
            'Houston',
            'TX',
            '77002',
            'US',
            '123mainst',
            'houston',
            'tx',
            '77002',
            null,
            null,
            null,
            'validated',
            'unit',
            '2025-01-01 00:00:00+00:00',
            '123mainst|houston|tx|77002|US|owner-1|vendor-1',
            $address->createdAt(),
            '2025-01-02 00:00:00+00:00',
            null,
            'fingerprint-gov',
            ['provider' => 'unit'],
            ['quality' => 95],
            true,
            'premise',
            95,
            'validator-suite',
            'validator',
            'run-gov',
            'canon-w08',
            ['line1' => '123 Main St'],
            ['line1Norm' => '123mainst'],
            'digest-gov',
            'duplicate',
            'addr-master',
            null,
            null,
            null,
            '2025-06-01 00:00:00+00:00',
            'quarterly',
            'unit',
            'validated',
            95
        );

        $this->service->update($duplicate);

        $saved = $this->service->get('addr-gov', 'owner-1', 'vendor-1');
        self::assertNotNull($saved);
        self::assertSame('duplicate', $saved->governanceStatus());
        self::assertSame('addr-master', $saved->duplicateOfId());
        self::assertNull($saved->supersededById());
        self::assertNull($saved->aliasOfId());
        self::assertNull($saved->conflictWithId());
        self::assertSame('2025-06-01 00:00:00+00:00', $saved->revalidationDueAt());
        self::assertSame('quarterly', $saved->revalidationPolicy());
        self::assertSame('unit', $saved->lastValidationProvider());
        self::assertSame('validated', $saved->lastValidationStatus());
        self::assertSame(95, $saved->lastValidationScore());
    }

    public function testInvalidLifecycleAndGovernanceTokensAreSanitizedOnPersist(): void
    {
        $address = new AddressData(
            'addr-sanitize',
            'owner-1',
            'vendor-1',
            '123 Main St',
            null,
            'Houston',
            'TX',
            '77002',
            'US',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'mystery-status',
            null,
            null,
            null,
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'unit-test',
            'odd-source',
            'fixture:addr-sanitize',
            'canon-w11',
            ['line1' => '123 Main St'],
            ['line1Norm' => '123mainst'],
            'sha256:addr-sanitize',
            'wild-status',
            null,
            null,
            null,
            null,
            '2025-07-01 00:00:00+00:00',
            'whenever',
            'validator-suite',
            'unclear',
            77
        );

        $this->service->create($address);

        $saved = $this->service->get('addr-sanitize', 'owner-1', 'vendor-1');
        self::assertNotNull($saved);
        self::assertSame('unknown', $saved->validationStatus());
        self::assertNull($saved->sourceType());
        self::assertSame('canonical', $saved->governanceStatus());
        self::assertNull($saved->revalidationPolicy());
        self::assertNull($saved->lastValidationStatus());
    }

    public function testTenantIsolationForGetUpdateAndSearch(): void
    {
        $tenantOne = $this->makeAddress('addr-7', ownerId: 'owner-1', vendorId: 'vendor-1');
        $tenantTwo = $this->makeAddress('addr-8', ownerId: 'owner-2', vendorId: 'vendor-2', line1: '987 Other St');
        $this->service->create($tenantOne);
        $this->service->create($tenantTwo);

        $foundOtherTenant = $this->service->get('addr-7', 'owner-2', 'vendor-2');
        static::assertNull($foundOtherTenant);

        $updateWrongTenant = $this->makeAddress('addr-7', ownerId: 'owner-2', vendorId: 'vendor-2', line1: 'Hacked St');
        $this->service->update($updateWrongTenant);

        $stillOriginal = $this->service->get('addr-7', 'owner-1', 'vendor-1');
        static::assertNotNull($stillOriginal);
        static::assertSame('123 Main St', $stillOriginal->line1());

        $results = $this->service->search('owner-2', 'vendor-2', null, null, 10, null);
        static::assertCount(1, $results['items']);
        static::assertSame('addr-8', $results['items'][0]->id());
    }

    private function outboxCount(): int
    {
        $count = $this->pdo->query('SELECT COUNT(*) FROM address_outbox')->fetchColumn();

        return (int) $count;
    }

    private function makeAddress(
        string $id,
        string $ownerId = 'owner-1',
        string $vendorId = 'vendor-1',
        string $line1 = '123 Main St',
        ?string $dedupeKey = null,
        ?string $updatedAt = null,
        ?string $line2 = null,
        string $city = 'Houston',
        ?string $region = 'TX',
        ?string $postalCode = '77002',
        string $countryCode = 'US',
        ?string $line1Norm = null,
        ?string $cityNorm = null,
        ?string $regionNorm = null,
        ?string $postalCodeNorm = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $geohash = null,
        string $validationStatus = 'pending',
        ?string $validationProvider = null,
        ?string $validatedAt = null,
        ?string $validationFingerprint = null,
        ?array $validationRaw = null,
        ?array $validationVerdict = null,
        ?bool $validationDeliverable = null,
        ?string $validationGranularity = null,
        ?int $validationQuality = null,
        ?string $sourceSystem = 'unit-test',
        ?string $sourceType = 'manual',
        mixed $sourceReference = '__DEFAULT__',
        ?string $normalizationVersion = 'canon-w15',
        mixed $rawInputSnapshot = '__DEFAULT__',
        mixed $normalizedSnapshot = '__DEFAULT__',
        mixed $providerDigest = '__DEFAULT__',
        string $governanceStatus = 'canonical',
        ?string $duplicateOfId = null,
        ?string $supersededById = null,
        ?string $aliasOfId = null,
        ?string $conflictWithId = null,
        ?string $revalidationDueAt = null,
        ?string $revalidationPolicy = null,
        ?string $lastValidationProvider = null,
        ?string $lastValidationStatus = null,
        ?int $lastValidationScore = null,
    ): AddressData {
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');
        $line1Norm ??= strtolower(str_replace(' ', '', $line1)).'-'.strtolower($id);
        $cityNorm ??= strtolower(str_replace(' ', '', $city));
        $regionNorm ??= null === $region ? null : strtolower(str_replace(' ', '', $region));
        $postalCodeNorm ??= null === $postalCode ? null : strtolower(str_replace(' ', '', $postalCode));
        $validationProvider ??= 'unit-test';
        $lastValidationProvider ??= $validationProvider;
        if ('__DEFAULT__' === $sourceReference) {
            $sourceReference = 'fixture:'.$id;
        }
        if ('__DEFAULT__' === $rawInputSnapshot) {
            $rawInputSnapshot = ['line1' => $line1, 'city' => $city, 'region' => $region, 'postalCode' => $postalCode, 'countryCode' => $countryCode];
        }
        if ('__DEFAULT__' === $normalizedSnapshot) {
            $normalizedSnapshot = ['line1Norm' => $line1Norm, 'cityNorm' => $cityNorm, 'regionNorm' => $regionNorm, 'postalCodeNorm' => $postalCodeNorm];
        }
        if ('__DEFAULT__' === $providerDigest) {
            $providerDigest = 'sha256:'.$id;
        }

        return new AddressData(
            $id,
            $ownerId,
            $vendorId,
            $line1,
            $line2,
            $city,
            $region,
            $postalCode,
            $countryCode,
            $line1Norm,
            $cityNorm,
            $regionNorm,
            $postalCodeNorm,
            $latitude,
            $longitude,
            $geohash,
            $validationStatus,
            $validationProvider,
            $validatedAt,
            $dedupeKey,
            $now,
            $updatedAt,
            null,
            $validationFingerprint,
            $validationRaw,
            $validationVerdict,
            $validationDeliverable,
            $validationGranularity,
            $validationQuality,
            $sourceSystem,
            $sourceType,
            $sourceReference,
            $normalizationVersion,
            $rawInputSnapshot,
            $normalizedSnapshot,
            $providerDigest,
            $governanceStatus,
            $duplicateOfId,
            $supersededById,
            $aliasOfId,
            $conflictWithId,
            $revalidationDueAt,
            $revalidationPolicy,
            $lastValidationProvider,
            $lastValidationStatus,
            $lastValidationScore
        );
    }

    private function schemaSql(): string
    {
        return <<<'SQL'
CREATE TABLE address_entity (
  id TEXT PRIMARY KEY,
  owner_id TEXT NULL,
  vendor_id TEXT NULL,
  line1 TEXT NOT NULL,
  line2 TEXT NULL,
  city TEXT NOT NULL,
  region TEXT NULL,
  postal_code TEXT NULL,
  country_code TEXT NOT NULL CHECK (length(country_code) = 2),
  line1_norm TEXT NULL,
  city_norm TEXT NULL,
  region_norm TEXT NULL,
  postal_code_norm TEXT NULL,
  latitude REAL NULL,
  longitude REAL NULL,
  geohash TEXT NULL,
  validation_status TEXT NOT NULL DEFAULT 'unknown'
    CHECK (validation_status IN ('unknown', 'pending', 'normalized', 'validated', 'rejected', 'uncertain', 'overridden')),
  validation_provider TEXT NULL,
  validated_at TEXT NULL,
  source_system TEXT NULL,
  source_type TEXT NULL
    CHECK (source_type IS NULL OR source_type IN ('manual', 'import', 'partner', 'validator', 'override', 'migration')),
  source_reference TEXT NULL,
  normalization_version TEXT NULL,
  raw_input_snapshot TEXT NULL,
  normalized_snapshot TEXT NULL,
  provider_digest TEXT NULL,
  governance_status TEXT NOT NULL DEFAULT 'canonical'
    CHECK (governance_status IN ('canonical', 'duplicate', 'superseded', 'alias', 'conflict')),
  duplicate_of_id TEXT NULL,
  superseded_by_id TEXT NULL,
  alias_of_id TEXT NULL,
  conflict_with_id TEXT NULL,
  revalidation_due_at TEXT NULL,
  revalidation_policy TEXT NULL
    CHECK (revalidation_policy IS NULL OR revalidation_policy IN ('manual', 'on-change', 'daily', 'weekly', 'monthly', 'quarterly', 'semiannual', 'annual')),
  last_validation_provider TEXT NULL,
  last_validation_status TEXT NULL
    CHECK (last_validation_status IS NULL OR last_validation_status IN ('normalized', 'validated', 'rejected', 'uncertain', 'overridden')),
  last_validation_score INTEGER NULL,
  dedupe_key TEXT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NULL,
  deleted_at TEXT NULL,
  validation_fingerprint TEXT NULL,
  validation_raw TEXT NULL,
  validation_verdict TEXT NULL,
  validation_deliverable INTEGER NULL,
  validation_granularity TEXT NULL,
  validation_quality INTEGER NULL
  ,CONSTRAINT address_tenant_scope_chk CHECK (owner_id IS NOT NULL OR vendor_id IS NOT NULL)
);

CREATE UNIQUE INDEX address_dedupe_unique
  ON address_entity (dedupe_key) WHERE dedupe_key IS NOT NULL;

CREATE TRIGGER trg_address_touch_updated_at
  AFTER UPDATE ON address_entity
  FOR EACH ROW
  WHEN NEW.updated_at IS OLD.updated_at
BEGIN
  UPDATE address_entity
    SET updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.id;
END;

CREATE TRIGGER trg_address_dedupe_autofill
  AFTER INSERT ON address_entity
  FOR EACH ROW
  WHEN NEW.dedupe_key IS NULL
BEGIN
  UPDATE address_entity
    SET dedupe_key = CASE
      WHEN coalesce(NEW.line1_norm, '') = ''
        AND coalesce(NEW.city_norm, '') = ''
        AND coalesce(NEW.region_norm, '') = ''
        AND coalesce(NEW.postal_code_norm, '') = ''
        AND coalesce(NEW.country_code, '') = '' THEN NULL
      ELSE lower(replace(coalesce(NEW.line1_norm, ''), ' ', '')) || '|' ||
        lower(replace(coalesce(NEW.city_norm, ''), ' ', '')) || '|' ||
        lower(replace(coalesce(NEW.region_norm, ''), ' ', '')) || '|' ||
        lower(replace(coalesce(NEW.postal_code_norm, ''), ' ', '')) || '|' ||
        upper(coalesce(NEW.country_code, ''))
      END
    WHERE id = NEW.id AND NEW.dedupe_key IS NULL;
END;

CREATE TRIGGER trg_address_dedupe_autofill_update
  AFTER UPDATE ON address_entity
  FOR EACH ROW
  WHEN NEW.dedupe_key IS NULL
BEGIN
  UPDATE address_entity
    SET dedupe_key = CASE
      WHEN coalesce(NEW.line1_norm, '') = ''
        AND coalesce(NEW.city_norm, '') = ''
        AND coalesce(NEW.region_norm, '') = ''
        AND coalesce(NEW.postal_code_norm, '') = ''
        AND coalesce(NEW.country_code, '') = '' THEN NULL
      ELSE lower(replace(coalesce(NEW.line1_norm, ''), ' ', '')) || '|' ||
        lower(replace(coalesce(NEW.city_norm, ''), ' ', '')) || '|' ||
        lower(replace(coalesce(NEW.region_norm, ''), ' ', '')) || '|' ||
        lower(replace(coalesce(NEW.postal_code_norm, ''), ' ', '')) || '|' ||
        upper(coalesce(NEW.country_code, ''))
      END
    WHERE id = NEW.id AND NEW.dedupe_key IS NULL;
END;

CREATE TABLE address_evidence_snapshot (
  id TEXT PRIMARY KEY,
  address_id TEXT NOT NULL,
  owner_id TEXT NULL,
  vendor_id TEXT NULL,
  source_system TEXT NULL,
  source_type TEXT NULL,
  source_reference TEXT NULL,
  validated_by TEXT NULL,
  validated_at TEXT NULL,
  normalization_version TEXT NULL,
  raw_input_snapshot TEXT NULL,
  normalized_snapshot TEXT NULL,
  validation_status TEXT NOT NULL
    CHECK (validation_status IN ('unknown', 'pending', 'normalized', 'validated', 'rejected', 'uncertain', 'overridden')),
  validation_score INTEGER NULL,
  validation_issues TEXT NULL,
  provider_digest TEXT NULL,
  created_at TEXT NOT NULL,
  CONSTRAINT address_evidence_snapshot_scope_chk CHECK (owner_id IS NOT NULL OR vendor_id IS NOT NULL)
);

CREATE INDEX address_evidence_snapshot_address_idx
  ON address_evidence_snapshot (address_id, created_at DESC, id DESC);

CREATE TABLE address_outbox (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  stream TEXT NOT NULL DEFAULT 'address',
  event_name TEXT NOT NULL,
  event_version INTEGER NOT NULL,
  payload TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at TEXT NULL,
  locked_at TEXT NULL,
  locked_by TEXT NULL,
  published_attempt INTEGER NOT NULL DEFAULT 0,
  last_error TEXT NULL
);
SQL;
    }
}
