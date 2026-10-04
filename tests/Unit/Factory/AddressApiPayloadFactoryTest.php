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
