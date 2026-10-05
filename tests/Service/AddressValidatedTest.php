<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Service;

use App\Addressing\Contract\Message\AddressValidated;
use App\Addressing\Contract\Message\AddressValidationVerdict;
use App\Addressing\Policy\AddressGovernancePolicy;
use PHPUnit\Framework\TestCase;

final class AddressValidatedTest extends TestCase
{
    public function testFingerprintStable(): void
    {
        $first = AddressValidated::fromArray([
            'line1Norm' => 'a',
            'cityNorm' => 'b',
            'validatedAt' => '2025-12-30T00:00:00Z',
        ]);
        $second = AddressValidated::fromArray([
            'line1Norm' => 'a',
            'cityNorm' => 'b',
            'validatedAt' => '2025-12-30T00:00:00Z',
        ]);

        self::assertSame($first->fingerprint(), $second->fingerprint());
    }

    public function testInvalidPerimeterValuesAreSanitized(): void
    {
        $validated = AddressValidated::fromArray([
            'sourceType' => 'strange-source',
            'governanceStatus' => 'wild',
            'revalidationPolicy' => 'sometimes',
            'lastValidationStatus' => 'mystery',
        ]);

        self::assertNull($validated->sourceType);
        self::assertSame('canonical', $validated->governanceStatus);
        self::assertNull($validated->revalidationPolicy);
        self::assertNull($validated->lastValidationStatus);
    }

    public function testVerdictInputCoercionAndBoundsArePreserved(): void
    {
        $verdict = AddressValidationVerdict::fromArray([
            'deliverable' => ' yes ',
            'granularity' => ' rooftop ',
            'quality' => '101.4',
            'signal' => ['source' => 'unit'],
        ]);

        self::assertInstanceOf(AddressValidationVerdict::class, $verdict);
        self::assertTrue($verdict->deliverable);
        self::assertSame('rooftop', $verdict->granularity);
        self::assertSame(100, $verdict->quality);
        self::assertSame(['source' => 'unit'], $verdict->signal);

        $bounded = AddressValidationVerdict::fromArray([
            'deliverable' => 2,
            'granularity' => 42,
            'quality' => '-4.7',
            'signal' => 'invalid',
        ]);

        self::assertInstanceOf(AddressValidationVerdict::class, $bounded);
        self::assertFalse($bounded->deliverable);
        self::assertNull($bounded->granularity);
        self::assertSame(0, $bounded->quality);
        self::assertSame([], $bounded->signal);
    }

    public function testGovernancePolicyNormalizesTransitionsAndCanonicalLinks(): void
    {
        self::assertSame([], AddressGovernancePolicy::normalizePatch('canonical', 'addr-1', []));

        self::assertSame([
            'governance_status' => 'duplicate',
            'duplicate_of_id' => 'addr-2',
            'superseded_by_id' => null,
            'alias_of_id' => null,
            'conflict_with_id' => null,
        ], AddressGovernancePolicy::normalizePatch('canonical', 'addr-1', [
            'governanceStatus' => 'duplicate',
            'duplicateOfId' => ' addr-2 ',
        ]));

        self::assertSame([
            'governance_status' => 'canonical',
            'duplicate_of_id' => null,
            'superseded_by_id' => null,
            'alias_of_id' => null,
            'conflict_with_id' => null,
        ], AddressGovernancePolicy::normalizePatch('conflict', 'addr-1', [
            'governanceStatus' => 'canonical',
        ]));
    }

    public function testGovernancePolicyRejectsInvalidAndSelfReferentialTransitions(): void
    {
        try {
            AddressGovernancePolicy::normalizePatch('duplicate', 'addr-1', [
                'governanceStatus' => 'canonical',
            ]);
            self::fail('Expected invalid governance transition to throw.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Invalid governance transition', $exception->getMessage());
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('requires a non-self link id');
        AddressGovernancePolicy::normalizePatch('canonical', 'addr-1', [
            'governanceStatus' => 'alias',
            'aliasOfId' => ' addr-1 ',
        ]);
    }

    public function testValidationVerdictLegacyAliasRemainsSupported(): void
    {
        $validated = AddressValidated::fromArray([
            'verdict' => 'invalid',
            'validationVerdict' => [
                'deliverable' => 'true',
                'quality' => 86.6,
            ],
            'raw' => 'invalid',
            'rawInput' => ['line1' => '123 Main St'],
        ]);

        self::assertInstanceOf(AddressValidationVerdict::class, $validated->addressValidationVerdict);
        self::assertTrue($validated->addressValidationVerdict->deliverable);
        self::assertSame(87, $validated->addressValidationVerdict->quality);
        self::assertNull($validated->raw);
        self::assertSame(['line1' => '123 Main St'], $validated->rawInput);
    }
}
