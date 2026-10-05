<?php

declare(strict_types=1);

namespace App\Addressing\Tests\Unit\Factory;

use App\Addressing\Factory\AddressApiPayloadFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class AddressApiPayloadFactoryTest extends TestCase
{
    public function testDecodeJsonRequestRequiresJsonObject(): void
    {
        $factory = new AddressApiPayloadFactory();

        self::assertSame(['line1' => 'Main'], $factory->decodeJsonRequest(Request::create('/', content: '{"line1":"Main"}')));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_json');

        $factory->decodeJsonRequest(Request::create('/', content: 'not-json'));
    }

    public function testCreateAddressEntityNormalizesInputAndDefaults(): void
    {
        $address = (new AddressApiPayloadFactory())->createAddressEntity([
            'line1' => '  123 Main St  ',
            'city' => '  Houston ',
            'countryCode' => ' us ',
            'latitude' => '29.7604',
            'longitude' => -95.3698,
            'validationStatus' => 'unknown-value',
            'sourceType' => 'unknown-value',
            'governanceStatus' => 'unknown-value',
            'lastValidationScore' => '91',
        ]);

        self::assertSame('123 Main St', $address->line1());
        self::assertSame('Houston', $address->city());
        self::assertSame('US', $address->countryCode());
        self::assertSame(29.7604, $address->latitude());
        self::assertSame(-95.3698, $address->longitude());
        self::assertSame('pending', $address->validationStatus());
        self::assertNull($address->sourceType());
        self::assertSame('canonical', $address->governanceStatus());
        self::assertSame(91, $address->lastValidationScore());
    }

    public function testRequireStringListTrimsDeduplicatesAndPreservesOrder(): void
    {
        $ids = (new AddressApiPayloadFactory())->requireStringList([
            'ids' => [' first ', 'second', 'first'],
        ], 'ids');

        self::assertSame(['first', 'second'], $ids);
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidStringListProvider')]
    public function testRequireStringListRejectsInvalidLists(array $payload, string $message): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($message);

        (new AddressApiPayloadFactory())->requireStringList($payload, 'ids');
    }

    /** @return iterable<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidStringListProvider(): iterable
    {
        yield 'missing' => [[], 'missing_ids'];
        yield 'not an array' => [['ids' => 'one'], 'missing_ids'];
        yield 'empty array' => [['ids' => []], 'invalid_ids'];
        yield 'blank element' => [['ids' => [' ']], 'invalid_ids'];
        yield 'non-string element' => [['ids' => [1]], 'invalid_ids'];
    }

    public function testPrivateCoercionHelpersCoverAllInputKinds(): void
    {
        $factory = new AddressApiPayloadFactory();
        $invoke = static function (string $methodName, array $arguments = []) use ($factory): mixed {
            $method = new \ReflectionMethod(AddressApiPayloadFactory::class, $methodName);

            return $method->invokeArgs($factory, $arguments);
        };

        self::assertSame('value', $invoke('reqStr', [['key' => ' value '], 'key']));
        foreach ([[], ['key' => 10], ['key' => '   ']] as $invalidRequired) {
            try {
                $invoke('reqStr', [$invalidRequired, 'key']);
                self::fail('Expected missing required string to throw.');
            } catch (\RuntimeException $exception) {
                self::assertSame('missing_key', $exception->getMessage());
            }
        }

        self::assertNull($invoke('optStr', [[], 'key']));
        self::assertNull($invoke('optStr', [['key' => null], 'key']));
        self::assertNull($invoke('optStr', [['key' => '  '], 'key']));
        self::assertSame('value', $invoke('optStr', [['key' => ' value '], 'key']));
        try {
            $invoke('optStr', [['key' => 1], 'key']);
            self::fail('Expected invalid optional string to throw.');
        } catch (\RuntimeException $exception) {
            self::assertSame('invalid_key', $exception->getMessage());
        }

        self::assertNull($invoke('optArray', [[], 'key']));
        self::assertNull($invoke('optArray', [['key' => null], 'key']));
        self::assertSame(['x' => 1], $invoke('optArray', [['key' => ['x' => 1]], 'key']));
        try {
            $invoke('optArray', [['key' => 'invalid'], 'key']);
            self::fail('Expected invalid optional array to throw.');
        } catch (\RuntimeException $exception) {
            self::assertSame('invalid_key', $exception->getMessage());
        }

        self::assertNull($invoke('lastValidationScore', [[]]));
        self::assertNull($invoke('lastValidationScore', [['lastValidationScore' => null]]));
        self::assertNull($invoke('lastValidationScore', [['lastValidationScore' => '']]));
        self::assertSame(88, $invoke('lastValidationScore', [['lastValidationScore' => 88]]));
        self::assertSame(89, $invoke('lastValidationScore', [['lastValidationScore' => '89.9']]));
        foreach ([['lastValidationScore' => 'bad'], ['lastValidationScore' => []]] as $invalidScore) {
            try {
                $invoke('lastValidationScore', [$invalidScore]);
                self::fail('Expected invalid validation score to throw.');
            } catch (\RuntimeException $exception) {
                self::assertSame('invalid_lastValidationScore', $exception->getMessage());
            }
        }

        self::assertNull($invoke('optFloat', [[], 'latitude']));
        self::assertNull($invoke('optFloat', [['latitude' => null], 'latitude']));
        self::assertNull($invoke('optFloat', [['latitude' => ''], 'latitude']));
        self::assertSame(10.0, $invoke('optFloat', [['latitude' => 10], 'latitude']));
        self::assertSame(10.5, $invoke('optFloat', [['latitude' => 10.5], 'latitude']));
        self::assertSame(11.25, $invoke('optFloat', [['latitude' => '11.25'], 'latitude']));
        foreach ([['latitude' => 'bad'], ['latitude' => []]] as $invalidFloat) {
            try {
                $invoke('optFloat', [$invalidFloat, 'latitude']);
                self::fail('Expected invalid optional float to throw.');
            } catch (\RuntimeException $exception) {
                self::assertSame('invalid_latitude', $exception->getMessage());
            }
        }

        self::assertSame('primary', $invoke('validationProviderInput', [[
            'provider' => ' primary ',
            'validationProvider' => 'fallback',
        ]]));
        self::assertSame('fallback', $invoke('validationProviderInput', [[
            'validationProvider' => ' fallback ',
        ]]));
        self::assertNull($invoke('validationProviderInput', [[]]));

        $validatedPayload = $invoke('validatedPayload', [[
            'line1Norm' => 'main st',
            'latitude' => '29.75',
            'provider' => 'unit',
            'rawInput' => ['line1' => 'Main St'],
            'lastValidationScore' => '90',
        ]]);
        self::assertIsArray($validatedPayload);
        self::assertSame('main st', $validatedPayload['line1Norm']);
        self::assertSame(29.75, $validatedPayload['latitude']);
        self::assertSame('unit', $validatedPayload['validationProvider']);
        self::assertSame(['line1' => 'Main St'], $validatedPayload['rawInput']);
        self::assertSame(90, $validatedPayload['lastValidationScore']);
    }

    public function testCreateAddressValidatedUsesProviderAliasAndNormalizedPayload(): void
    {
        $validated = (new AddressApiPayloadFactory())->createAddressValidated([
            'line1Norm' => 'main st',
            'cityNorm' => 'houston',
            'provider' => 'provider-primary',
            'validationProvider' => 'provider-fallback',
            'latitude' => 29.7604,
            'lastValidationScore' => 93,
        ]);

        self::assertSame('main st', $validated->line1Norm);
        self::assertSame('houston', $validated->cityNorm);
        self::assertSame('provider-primary', $validated->validationProvider);
        self::assertSame(29.7604, $validated->latitude);
        self::assertSame(93, $validated->lastValidationScore);
    }

    public function testOperationalPatchCoercesScoreAndRejectsInvalidScore(): void
    {
        $factory = new AddressApiPayloadFactory();

        $patch = $factory->operationalPatch([
            'governanceStatus' => ' duplicate ',
            'lastValidationScore' => '87',
        ]);

        self::assertSame('duplicate', $patch['governanceStatus']);
        self::assertSame(87, $patch['lastValidationScore']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid_lastValidationScore');

        $factory->operationalPatch(['lastValidationScore' => []]);
    }
}
