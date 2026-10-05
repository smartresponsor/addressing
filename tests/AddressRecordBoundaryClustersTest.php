<?php

declare(strict_types=1);

namespace Tests;

use App\Addressing\Value\Record\AddressData;
use App\Addressing\Value\Record\AddressGovernanceState;
use App\Addressing\Value\Record\AddressRecord;
use App\Addressing\Value\Record\AddressRevalidationState;
use App\Addressing\Value\Record\AddressValidationState;
use PHPUnit\Framework\TestCase;

final class AddressRecordBoundaryClustersTest extends TestCase
{
    public function testAddressRecordExposesConstructedBoundaryClusters(): void
    {
        $address = new AddressRecord(
            'record-1',
            'owner-1',
            null,
            '123 Main St',
            'Suite 5',
            'Houston',
            'TX',
            '77002',
            'US',
            '123 MAIN ST',
            'HOUSTON',
            'TX',
            '77002',
            29.7604,
            -95.3698,
            '9vk1m',
            'validated',
            'validator-a',
            '2026-10-05T12:00:00+00:00',
            'dedupe-record-1',
            '2026-10-05T11:00:00+00:00',
            '2026-10-05T12:30:00+00:00',
            null,
            'fingerprint-record-1',
            ['provider' => 'validator-a'],
            ['deliverable' => true],
            true,
            'premise',
            97,
            'erp',
            'api',
            'source-1',
            'v3',
            ['line1' => '123 Main St'],
            ['line1Norm' => '123 MAIN ST'],
            'digest-record-1',
            'alias',
            null,
            null,
            'canonical-1',
            null,
            '2026-11-01T00:00:00+00:00',
            'monthly',
            'validator-a',
            'validated',
            97,
        );

        $validationState = $address->validationState();
        self::assertSame('validated', $validationState->validationStatus());
        self::assertSame('validator-a', $validationState->validationProvider());
        self::assertSame('digest-record-1', $validationState->providerDigest());

        $governanceState = $address->governanceState();
        self::assertSame('alias', $governanceState->governanceStatus());
        self::assertSame('canonical-1', $governanceState->aliasOfId());

        $revalidationState = $address->revalidationState();
        self::assertSame('monthly', $revalidationState->revalidationPolicy());
        self::assertSame(97, $revalidationState->lastValidationScore());

        self::assertSame('record-1', $address->id());
        self::assertSame('owner-1', $address->ownerId());
        self::assertNull($address->vendorId());
        self::assertSame('2026-10-05T11:00:00+00:00', $address->createdAt());
        self::assertSame('2026-10-05T12:30:00+00:00', $address->updatedAt());
        self::assertNull($address->deletedAt());
    }

    public function testAddressDataExposesValidationGovernanceAndRevalidationClusters(): void
    {
        $address = new AddressData(
            'addr-1',
            'owner-1',
            'vendor-1',
            '123 Main St',
            'Suite 200',
            'Houston',
            'TX',
            '77002',
            'US',
            '123 MAIN ST',
            'HOUSTON',
            'TX',
            '77002',
            29.7604,
            -95.3698,
            '9vk1m',
            'validated',
            'demo-validator',
            '2026-04-22T10:00:00+00:00',
            'dedupe-1',
            '2026-04-22T09:00:00+00:00',
            null,
            null,
            'fingerprint-1',
            ['provider' => 'demo-validator'],
            ['confidence' => 98],
            true,
            'premise',
            98,
            'erp',
            'api',
            'ext-123',
            'v2',
            ['line1' => '123 Main St'],
            ['city' => 'HOUSTON'],
            'digest-1',
            'duplicate',
            'addr-0',
            null,
            null,
            null,
            '2026-05-01T10:00:00+00:00',
            'rolling-30d',
            'demo-validator',
            'validated',
            98,
        );

        $validationState = $address->validationState();
        self::assertInstanceOf(AddressValidationState::class, $validationState);
        self::assertSame('validated', $validationState->validationStatus());
        self::assertSame('demo-validator', $validationState->validationProvider());
        self::assertSame('2026-04-22T10:00:00+00:00', $validationState->validatedAt());
        self::assertSame('fingerprint-1', $validationState->validationFingerprint());
        self::assertSame(['provider' => 'demo-validator'], $validationState->validationRaw());
        self::assertSame(['confidence' => 98], $validationState->validationVerdict());
        self::assertTrue($validationState->validationDeliverable());
        self::assertSame('premise', $validationState->validationGranularity());
        self::assertSame(98, $validationState->validationQuality());
        self::assertSame('erp', $validationState->sourceSystem());
        self::assertSame('api', $validationState->sourceType());
        self::assertSame('ext-123', $validationState->sourceReference());
        self::assertSame('v2', $validationState->normalizationVersion());
        self::assertSame(['line1' => '123 Main St'], $validationState->rawInputSnapshot());
        self::assertSame(['city' => 'HOUSTON'], $validationState->normalizedSnapshot());
        self::assertSame('digest-1', $validationState->providerDigest());

        $governanceState = $address->governanceState();
        self::assertInstanceOf(AddressGovernanceState::class, $governanceState);
        self::assertSame('duplicate', $governanceState->governanceStatus());
        self::assertSame('addr-0', $governanceState->duplicateOfId());
        self::assertNull($governanceState->supersededById());
        self::assertNull($governanceState->aliasOfId());
        self::assertNull($governanceState->conflictWithId());

        $revalidationState = $address->revalidationState();
        self::assertInstanceOf(AddressRevalidationState::class, $revalidationState);
        self::assertSame('2026-05-01T10:00:00+00:00', $revalidationState->revalidationDueAt());
        self::assertSame('rolling-30d', $revalidationState->revalidationPolicy());
        self::assertSame('demo-validator', $revalidationState->lastValidationProvider());
        self::assertSame('validated', $revalidationState->lastValidationStatus());
        self::assertSame(98, $revalidationState->lastValidationScore());
    }
}
