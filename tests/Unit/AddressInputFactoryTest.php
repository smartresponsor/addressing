<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests;

use App\Addressing\DTO\AddressManageDTO;
use App\Addressing\Factory\AddressInputFactory;
use PHPUnit\Framework\TestCase;

final class AddressInputFactoryTest extends TestCase
{
    public function testFromManageDtoHandlesMinimalNullableInputAndInvalidOverrides(): void
    {
        $dto = new AddressManageDTO();
        $dto->line1 = '  10 Oak Rd  ';
        $dto->line2 = '   ';
        $dto->city = ' Austin ';
        $dto->region = null;
        $dto->postalCode = null;
        $dto->countryCode = 'us';
        $dto->ownerId = null;
        $dto->vendorId = '   ';

        $record = (new AddressInputFactory())->fromManageDto($dto, [
            'id' => 123,
            'latitude' => 'not-a-number',
            'longitude' => [],
            'validationDeliverable' => 'yes',
            'validationRaw' => 'invalid',
            'validationVerdict' => 'invalid',
            'rawInputSnapshot' => 'invalid',
            'normalizedSnapshot' => 'invalid',
            'validationQuality' => 'invalid',
            'lastValidationScore' => [],
            'sourceType' => 'wild',
            'governanceStatus' => 'wild',
            'revalidationPolicy' => 'sometimes',
        ]);

        self::assertNull($record->ownerId());
        self::assertNull($record->vendorId());
        self::assertNull($record->line2());
        self::assertNull($record->region());
        self::assertNull($record->postalCode());
        self::assertNull($record->regionNorm());
        self::assertNull($record->postalCodeNorm());
        self::assertNull($record->latitude());
        self::assertNull($record->longitude());
        self::assertNull($record->validationDeliverable());
        self::assertNull($record->validationRaw());
        self::assertNull($record->validationVerdict());
        self::assertNull($record->validationQuality());
        self::assertNull($record->lastValidationScore());
        self::assertNull($record->sourceType());
        self::assertSame('canonical', $record->governanceStatus());
        self::assertNull($record->revalidationPolicy());
        self::assertSame('10 oak rd|austin|us', $record->dedupeKey());
        self::assertSame('10 Oak Rd', $record->rawInputSnapshot()['line1'] ?? null);
        self::assertSame('austin', $record->normalizedSnapshot()['cityNorm'] ?? null);
    }

    public function testFromManageDtoCoercesRichOptionalOverrides(): void
    {
        $dto = new AddressManageDTO();
        $dto->line1 = '500 Test Ave';
        $dto->line2 = null;
        $dto->city = 'Dallas';
        $dto->region = 'tx';
        $dto->postalCode = '75201';
        $dto->countryCode = 'us';
        $dto->ownerId = 'owner-2';
        $dto->vendorId = null;

        $record = (new AddressInputFactory())->fromManageDto($dto, [
            'id' => 'address-rich',
            'latitude' => 32,
            'longitude' => '-96.7970',
            'validationDeliverable' => true,
            'validationRaw' => ['provider' => 'demo'],
            'validationVerdict' => ['quality' => 92],
            'validationQuality' => 91.9,
            'lastValidationScore' => '87',
            'rawInputSnapshot' => ['custom' => true],
            'normalizedSnapshot' => ['customNorm' => true],
            'validationFingerprint' => 'fingerprint-rich',
            'validationGranularity' => 'rooftop',
            'sourceSystem' => 'test-suite',
            'sourceType' => 'provider',
            'sourceReference' => 'run-42',
            'normalizationVersion' => 'v9',
            'providerDigest' => 'digest-rich',
            'governanceStatus' => 'duplicate',
            'duplicateOfId' => 'address-canonical',
            'revalidationPolicy' => 'monthly',
            'lastValidationProvider' => 'provider-a',
            'lastValidationStatus' => 'validated',
        ]);

        self::assertSame(32.0, $record->latitude());
        self::assertSame(-96.797, $record->longitude());
        self::assertTrue($record->validationDeliverable());
        self::assertSame(['provider' => 'demo'], $record->validationRaw());
        self::assertSame(['quality' => 92], $record->validationVerdict());
        self::assertSame(91, $record->validationQuality());
        self::assertSame(87, $record->lastValidationScore());
        self::assertSame(['custom' => true], $record->rawInputSnapshot());
        self::assertSame(['customNorm' => true], $record->normalizedSnapshot());
        self::assertSame('fingerprint-rich', $record->validationFingerprint());
        self::assertSame('rooftop', $record->validationGranularity());
        self::assertSame('test-suite', $record->sourceSystem());
        self::assertNull($record->sourceType());
        self::assertSame('run-42', $record->sourceReference());
        self::assertSame('v9', $record->normalizationVersion());
        self::assertSame('digest-rich', $record->providerDigest());
        self::assertSame('duplicate', $record->governanceStatus());
        self::assertSame('address-canonical', $record->duplicateOfId());
        self::assertSame('monthly', $record->revalidationPolicy());
        self::assertSame('provider-a', $record->lastValidationProvider());
        self::assertSame('validated', $record->lastValidationStatus());
    }

    public function testPrivateHelpersCoverAllCoercionAndNormalizationBranches(): void
    {
        $factory = new AddressInputFactory();
        $invoke = static function (string $methodName, array $arguments = []) use ($factory): mixed {
            $method = new \ReflectionMethod(AddressInputFactory::class, $methodName);

            return $method->invokeArgs($factory, $arguments);
        };

        self::assertNull($invoke('stringOverride', [[], 'key']));
        self::assertSame('value', $invoke('stringOverride', [['key' => 'value'], 'key']));
        self::assertNull($invoke('stringOverride', [['key' => 10], 'key']));

        self::assertNull($invoke('intOverride', [[], 'key']));
        self::assertSame(7, $invoke('intOverride', [['key' => 7], 'key']));
        self::assertSame(7, $invoke('intOverride', [['key' => 7.9], 'key']));
        self::assertSame(8, $invoke('intOverride', [['key' => '8'], 'key']));
        self::assertNull($invoke('intOverride', [['key' => 'bad'], 'key']));
        self::assertNull($invoke('intOverride', [['key' => []], 'key']));

        self::assertNull($invoke('floatOverride', [[], 'key']));
        self::assertSame(7.0, $invoke('floatOverride', [['key' => 7], 'key']));
        self::assertSame(7.9, $invoke('floatOverride', [['key' => 7.9], 'key']));
        self::assertSame(8.5, $invoke('floatOverride', [['key' => '8.5'], 'key']));
        self::assertNull($invoke('floatOverride', [['key' => 'bad'], 'key']));
        self::assertNull($invoke('floatOverride', [['key' => []], 'key']));

        self::assertNull($invoke('nullableTrimmed', [null]));
        self::assertNull($invoke('nullableTrimmed', ['   ']));
        self::assertSame('value', $invoke('nullableTrimmed', [' value ']));

        $dto = new AddressManageDTO();
        $dto->postalCode = null;
        $dto->region = null;
        self::assertNull($invoke('postalCode', [$dto]));
        self::assertNull($invoke('region', [$dto]));
        $dto->postalCode = '   ';
        $dto->region = '   ';
        self::assertNull($invoke('postalCode', [$dto]));
        self::assertNull($invoke('region', [$dto]));
        $dto->postalCode = '770 02';
        $dto->region = 'tx';
        self::assertSame('770 02', $invoke('postalCode', [$dto]));
        self::assertSame('TX', $invoke('region', [$dto]));

        self::assertSame([
            'line1Norm' => 'main st',
            'cityNorm' => 'houston',
            'regionNorm' => null,
            'postalCodeNorm' => null,
        ], $invoke('normalizedAddress', ['Main St', 'Houston', null, null]));
        self::assertSame([
            'line1Norm' => 'main st',
            'cityNorm' => 'houston',
            'regionNorm' => 'tx',
            'postalCodeNorm' => '77002',
        ], $invoke('normalizedAddress', ['Main St', 'Houston', 'TX', '770 02']));

        self::assertSame(
            'main st|houston|us',
            $invoke('dedupeKey', [[
                'line1Norm' => 'main st',
                'cityNorm' => 'houston',
                'regionNorm' => null,
                'postalCodeNorm' => null,
            ], 'US', null, null]),
        );
        self::assertSame(
            'main st|houston|tx|77002|us|owner-1|vendor-1',
            $invoke('dedupeKey', [[
                'line1Norm' => 'main st',
                'cityNorm' => 'houston',
                'regionNorm' => 'tx',
                'postalCodeNorm' => '77002',
            ], 'US', 'owner-1', 'vendor-1']),
        );
    }

    public function testFromManageDtoPreservesNormalizedRecordContract(): void
    {
        $dto = new AddressManageDTO();
        $dto->line1 = '123 Main St.';
        $dto->line2 = '  Suite 200  ';
        $dto->city = '  Houston  ';
        $dto->region = 'tx';
        $dto->postalCode = '770 02';
        $dto->countryCode = 'us';
        $dto->ownerId = '  owner-1  ';
        $dto->vendorId = '  vendor-1  ';

        $record = (new AddressInputFactory())->fromManageDto($dto, [
            'id' => 'address-1',
            'createdAt' => '2026-10-04 07:30:00+00:00',
            'latitude' => '29.7604',
            'longitude' => -95.3698,
            'sourceSystem' => 'test-suite',
            'normalizationVersion' => 'test-v1',
        ]);

        self::assertSame('address-1', $record->id());
        self::assertSame('owner-1', $record->ownerId());
        self::assertSame('vendor-1', $record->vendorId());
        self::assertSame('Suite 200', $record->line2());
        self::assertSame('Houston', $record->city());
        self::assertSame('TX', $record->region());
        self::assertSame('US', $record->countryCode());
        self::assertSame('123 main st.', $record->line1Norm());
        self::assertSame('houston', $record->cityNorm());
        self::assertSame('tx', $record->regionNorm());
        self::assertSame('77002', $record->postalCodeNorm());
        self::assertSame(29.7604, $record->latitude());
        self::assertSame(-95.3698, $record->longitude());
        self::assertSame('123 main st.|houston|tx|77002|us|owner-1|vendor-1', $record->dedupeKey());
        self::assertSame('2026-10-04 07:30:00+00:00', $record->createdAt());
        self::assertSame('test-suite', $record->sourceSystem());
        self::assertSame('test-v1', $record->normalizationVersion());
    }
}
