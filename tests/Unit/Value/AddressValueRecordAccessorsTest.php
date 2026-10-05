<?php

declare(strict_types=1);

namespace App\Addressing\Tests\Unit\Value;

use App\Addressing\Value\Record\AddressEvidenceSnapshotData;
use App\Addressing\Value\Record\AddressEvidenceSnapshotRecord;
use App\Addressing\Value\Record\AddressGovernanceState;
use App\Addressing\Value\Record\AddressRevalidationState;
use App\Addressing\Value\Record\AddressValidationState;
use PHPUnit\Framework\TestCase;

final class AddressValueRecordAccessorsTest extends TestCase
{
    public function testEvidenceSnapshotRecordExposesConstructorState(): void
    {
        $record = new AddressEvidenceSnapshotRecord(
            id: 'snapshot-1',
            addressId: 'address-1',
            ownerId: 'owner-1',
            vendorId: 'vendor-1',
            sourceSystem: 'validator',
            sourceType: 'partner',
            sourceReference: 'ref-1',
            validatedBy: 'validator-1',
            validatedAt: '2026-10-05T12:00:00+00:00',
            normalizationVersion: 'canon-v2',
            rawInputSnapshot: ['line1' => '123 Main St'],
            normalizedSnapshot: ['line1' => '123 MAIN ST'],
            validationStatus: 'validated',
            validationScore: 97,
            validationIssues: ['warnings' => []],
            providerDigest: 'sha256:provider',
            createdAt: '2026-10-05T12:01:00+00:00',
        );

        self::assertSame('snapshot-1', $record->id());
        self::assertSame('address-1', $record->addressId());
        self::assertSame('owner-1', $record->ownerId());
        self::assertSame('vendor-1', $record->vendorId());
        self::assertSame('validator', $record->sourceSystem());
        self::assertSame('partner', $record->sourceType());
        self::assertSame('ref-1', $record->sourceReference());
        self::assertSame('validator-1', $record->validatedBy());
        self::assertSame('2026-10-05T12:00:00+00:00', $record->validatedAt());
        self::assertSame('canon-v2', $record->normalizationVersion());
        self::assertSame(['line1' => '123 Main St'], $record->rawInputSnapshot());
        self::assertSame(['line1' => '123 MAIN ST'], $record->normalizedSnapshot());
        self::assertSame('validated', $record->validationStatus());
        self::assertSame(97, $record->validationScore());
        self::assertSame(['warnings' => []], $record->validationIssues());
        self::assertSame('sha256:provider', $record->providerDigest());
        self::assertSame('2026-10-05T12:01:00+00:00', $record->createdAt());
    }

    public function testValidationStateExposesConstructorState(): void
    {
        $state = new AddressValidationState(
            validationStatus: 'validated',
            validationProvider: 'provider-a',
            validatedAt: '2026-10-05T12:00:00+00:00',
            validationFingerprint: 'fingerprint-1',
            validationRaw: ['raw' => true],
            validationVerdict: ['quality' => 94],
            validationDeliverable: true,
            validationGranularity: 'premise',
            validationQuality: 94,
            sourceSystem: 'api',
            sourceType: 'validator',
            sourceReference: 'request-1',
            normalizationVersion: 'canon-v2',
            rawInputSnapshot: ['city' => 'Houston'],
            normalizedSnapshot: ['city' => 'HOUSTON'],
            providerDigest: 'sha256:validation',
        );

        self::assertSame('validated', $state->validationStatus());
        self::assertSame('provider-a', $state->validationProvider());
        self::assertSame('2026-10-05T12:00:00+00:00', $state->validatedAt());
        self::assertSame('fingerprint-1', $state->validationFingerprint());
        self::assertSame(['raw' => true], $state->validationRaw());
        self::assertSame(['quality' => 94], $state->validationVerdict());
        self::assertTrue($state->validationDeliverable());
        self::assertSame('premise', $state->validationGranularity());
        self::assertSame(94, $state->validationQuality());
        self::assertSame('api', $state->sourceSystem());
        self::assertSame('validator', $state->sourceType());
        self::assertSame('request-1', $state->sourceReference());
        self::assertSame('canon-v2', $state->normalizationVersion());
        self::assertSame(['city' => 'Houston'], $state->rawInputSnapshot());
        self::assertSame(['city' => 'HOUSTON'], $state->normalizedSnapshot());
        self::assertSame('sha256:validation', $state->providerDigest());
    }

    public function testEvidenceSnapshotDataExposesConstructorState(): void
    {
        $data = new AddressEvidenceSnapshotData(
            id: 'snapshot-data-1',
            addressId: 'address-data-1',
            ownerId: 'owner-data-1',
            vendorId: 'vendor-data-1',
            sourceSystem: 'batch',
            sourceType: 'import',
            sourceReference: 'batch-1',
            validatedBy: 'validator-data-1',
            validatedAt: '2026-10-05T13:00:00+00:00',
            normalizationVersion: 'canon-v3',
            rawInputSnapshot: ['postalCode' => '77001'],
            normalizedSnapshot: ['postalCode' => '77001'],
            validationStatus: 'validated',
            validationScore: 99,
            validationIssues: ['errors' => []],
            providerDigest: 'sha256:data',
            createdAt: '2026-10-05T13:01:00+00:00',
        );

        self::assertSame('snapshot-data-1', $data->id());
        self::assertSame('address-data-1', $data->addressId());
        self::assertSame('owner-data-1', $data->ownerId());
        self::assertSame('vendor-data-1', $data->vendorId());
        self::assertSame('batch', $data->sourceSystem());
        self::assertSame('import', $data->sourceType());
        self::assertSame('batch-1', $data->sourceReference());
        self::assertSame('validator-data-1', $data->validatedBy());
        self::assertSame('2026-10-05T13:00:00+00:00', $data->validatedAt());
        self::assertSame('canon-v3', $data->normalizationVersion());
        self::assertSame(['postalCode' => '77001'], $data->rawInputSnapshot());
        self::assertSame(['postalCode' => '77001'], $data->normalizedSnapshot());
        self::assertSame('validated', $data->validationStatus());
        self::assertSame(99, $data->validationScore());
        self::assertSame(['errors' => []], $data->validationIssues());
        self::assertSame('sha256:data', $data->providerDigest());
        self::assertSame('2026-10-05T13:01:00+00:00', $data->createdAt());
    }

    public function testGovernanceAndRevalidationStatesExposeConstructorState(): void
    {
        $governance = new AddressGovernanceState(
            governanceStatus: 'duplicate',
            duplicateOfId: 'address-duplicate',
            supersededById: 'address-successor',
            aliasOfId: 'address-alias',
            conflictWithId: 'address-conflict',
        );
        $revalidation = new AddressRevalidationState(
            revalidationDueAt: '2026-11-01T00:00:00+00:00',
            revalidationPolicy: 'monthly',
            lastValidationProvider: 'provider-b',
            lastValidationStatus: 'validated',
            lastValidationScore: 96,
        );

        self::assertSame('duplicate', $governance->governanceStatus());
        self::assertSame('address-duplicate', $governance->duplicateOfId());
        self::assertSame('address-successor', $governance->supersededById());
        self::assertSame('address-alias', $governance->aliasOfId());
        self::assertSame('address-conflict', $governance->conflictWithId());
        self::assertSame('2026-11-01T00:00:00+00:00', $revalidation->revalidationDueAt());
        self::assertSame('monthly', $revalidation->revalidationPolicy());
        self::assertSame('provider-b', $revalidation->lastValidationProvider());
        self::assertSame('validated', $revalidation->lastValidationStatus());
        self::assertSame(96, $revalidation->lastValidationScore());
    }
}
