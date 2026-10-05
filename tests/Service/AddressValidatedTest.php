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

    public function testMessageSerializationAndCoercionBranches(): void
    {
        $date = new \DateTimeImmutable('2026-01-02T03:04:05+00:00');
        $stringable = new class () implements \Stringable {
            public function __toString(): string
            {
                return ' stringable-value ';
            }
        };

        $validated = AddressValidated::fromArray([
            'line1Norm' => $stringable,
            'cityNorm' => 123,
            'regionNorm' => false,
            'postalCodeNorm' => ' ',
            'latitude' => 29,
            'longitude' => '-95.5',
            'validationProvider' => true,
            'validatedAt' => $date,
            'dedupeKey' => 42.5,
            'raw' => ['provider' => 'demo'],
            'verdict' => [
                'deliverable' => true,
                'granularity' => 'premise',
                'quality' => 95,
            ],
            'rawInput' => ['line1' => 'Main St'],
            'normalizedSnapshot' => ['line1Norm' => 'main st'],
            'revalidationDueAt' => 0,
            'lastValidationScore' => 88.9,
        ]);

        self::assertSame('stringable-value', $validated->line1Norm);
        self::assertSame('123', $validated->cityNorm);
        self::assertNull($validated->regionNorm);
        self::assertNull($validated->postalCodeNorm);
        self::assertSame(29.0, $validated->latitude);
        self::assertSame(-95.5, $validated->longitude);
        self::assertSame('1', $validated->validationProvider);
        self::assertSame($date->format(DATE_ATOM), $validated->validatedAt?->format(DATE_ATOM));
        self::assertSame('42.5', $validated->dedupeKey);
        self::assertSame(88, $validated->lastValidationScore);
        self::assertSame('1970-01-01T00:00:00+00:00', $validated->revalidationDueAt?->format(DATE_ATOM));

        $serialized = $validated->jsonSerialize();
        self::assertSame(['provider' => 'demo'], $serialized['raw']);
        self::assertSame(95, $serialized['verdict']['quality'] ?? null);

        $db = $validated->toDbArray();
        self::assertSame('{"provider":"demo"}', $db['validation_raw']);
        self::assertSame('{"deliverable":true,"granularity":"premise","quality":95,"signal":[]}', $db['validation_verdict']);
        self::assertSame('{"line1":"Main St"}', $db['raw_input_snapshot']);
        self::assertSame('{"line1Norm":"main st"}', $db['normalized_snapshot']);
        self::assertSame(95, $db['validation_quality']);

        $empty = AddressValidated::fromArray([]);
        $emptyDb = $empty->toDbArray();
        self::assertNull($emptyDb['validation_raw']);
        self::assertNull($emptyDb['validation_verdict']);
        self::assertNull($emptyDb['raw_input_snapshot']);
        self::assertNull($emptyDb['normalized_snapshot']);
    }

    public function testPrivateCoercionHelpersCoverInvalidAndAlternateInputs(): void
    {
        $invoke = static function (string $methodName, array $arguments = []): mixed {
            $method = new \ReflectionMethod(AddressValidated::class, $methodName);

            return $method->invokeArgs(null, $arguments);
        };

        self::assertNull($invoke('asNullableArray', ['invalid']));
        self::assertSame(['x' => 1], $invoke('asNullableArray', [['x' => 1]]));

        self::assertNull($invoke('asNullableString', [null]));
        self::assertNull($invoke('asNullableString', ['  ']));
        self::assertNull($invoke('asNullableString', [[]]));
        self::assertSame('12', $invoke('asNullableString', [12]));
        self::assertSame('1.5', $invoke('asNullableString', [1.5]));

        self::assertNull($invoke('asNullableFloat', [null]));
        self::assertNull($invoke('asNullableFloat', ['']));
        self::assertSame(4.0, $invoke('asNullableFloat', [4]));
        self::assertSame(4.5, $invoke('asNullableFloat', [4.5]));
        self::assertSame(5.25, $invoke('asNullableFloat', ['5.25']));
        self::assertNull($invoke('asNullableFloat', ['invalid']));

        self::assertNull($invoke('asNullableInt', [null]));
        self::assertNull($invoke('asNullableInt', ['']));
        self::assertSame(4, $invoke('asNullableInt', [4]));
        self::assertSame(4, $invoke('asNullableInt', [4.9]));
        self::assertNull($invoke('asNullableInt', ['  ']));
        self::assertSame(-7, $invoke('asNullableInt', [' -7 ']));
        self::assertNull($invoke('asNullableInt', ['7.5']));
        self::assertNull($invoke('asNullableInt', [[]]));

        self::assertNull($invoke('asNullableDate', [null]));
        self::assertNull($invoke('asNullableDate', ['']));
        self::assertInstanceOf(\DateTimeImmutable::class, $invoke('asNullableDate', [new \DateTime('2026-01-01T00:00:00+00:00')]));
        self::assertSame('2026-01-01T00:00:00+00:00', $invoke('asNullableDate', ['2026-01-01T00:00:00+00:00'])?->format(DATE_ATOM));
        self::assertSame('1970-01-01T00:00:01+00:00', $invoke('asNullableDate', [1])?->format(DATE_ATOM));
        self::assertNull($invoke('asNullableDate', [[]]));

        self::assertSame(['deliverable' => true], $invoke('validationVerdictData', [[
            'verdict' => ['deliverable' => true],
            'validationVerdict' => ['deliverable' => false],
        ]]));
        self::assertSame(['deliverable' => false], $invoke('validationVerdictData', [[
            'verdict' => 'invalid',
            'validationVerdict' => ['deliverable' => false],
        ]]));
        self::assertNull($invoke('validationVerdictData', [[]]));
    }

    public function testGovernancePolicyPrivateHelpersCoverAllScalarAndLinkPaths(): void
    {
        $invoke = static function (string $methodName, array $arguments): mixed {
            $method = new \ReflectionMethod(AddressGovernancePolicy::class, $methodName);

            return $method->invokeArgs(null, $arguments);
        };

        self::assertNull($invoke('asNullableString', [[]]));
        self::assertNull($invoke('asNullableString', ['   ']));
        self::assertSame('42', $invoke('asNullableString', [42]));
        self::assertSame('target', $invoke('asNullableString', [' target ']));

        self::assertNull($invoke('sanitizeLink', [null, 'addr-1']));
        self::assertNull($invoke('sanitizeLink', ['addr-2', '   ']));
        self::assertNull($invoke('sanitizeLink', [' addr-1 ', 'addr-1']));
        self::assertSame('addr-2', $invoke('sanitizeLink', [' addr-2 ', 'addr-1']));
    }

    public function testValidationVerdictPrivateHelpersCoverAllCoercionBranches(): void
    {
        self::assertNull(AddressValidationVerdict::fromArray(null));

        $invoke = static function (string $methodName, mixed $value): mixed {
            $method = new \ReflectionMethod(AddressValidationVerdict::class, $methodName);

            return $method->invoke(null, $value);
        };

        foreach ([true, 1, 1.0, '1', 'true', 'yes'] as $truthy) {
            self::assertTrue($invoke('asNullableBool', $truthy));
        }
        foreach ([false, 0, 0.0, '0', 'false', 'no'] as $falsey) {
            self::assertFalse($invoke('asNullableBool', $falsey));
        }
        self::assertNull($invoke('asNullableBool', []));
        self::assertNull($invoke('asNullableBool', 'unknown'));

        self::assertNull($invoke('asNullableString', null));
        self::assertNull($invoke('asNullableString', '   '));
        self::assertSame('rooftop', $invoke('asNullableString', ' rooftop '));

        self::assertSame(50, $invoke('asQualityScore', 50));
        self::assertSame(51, $invoke('asQualityScore', 50.6));
        self::assertSame(51, $invoke('asQualityScore', '50.6'));
        self::assertSame(100, $invoke('asQualityScore', 101));
        self::assertSame(0, $invoke('asQualityScore', -1));
        self::assertNull($invoke('asQualityScore', 'bad'));
        self::assertNull($invoke('asQualityScore', []));

        self::assertSame([], $invoke('asSignal', 'invalid'));
        self::assertSame(['provider' => 'unit'], $invoke('asSignal', ['provider' => 'unit']));
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
