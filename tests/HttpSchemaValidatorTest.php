<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests;

use App\Addressing\Validator\AddressSchemaValidator;
use PHPUnit\Framework\TestCase;

final class HttpSchemaValidatorTest extends TestCase
{
    public function testValidateRejectsWrongType(): void
    {
        $validator = new AddressSchemaValidator();

        $result = $validator->validate('ParseRequest', [
            'text' => '221B Baker Street',
            'countryHint' => 100,
        ]);

        self::assertSame(['ok' => false, 'error' => 'type_countryHint'], $result);
    }

    public function testValidateRejectsNullInRequiredField(): void
    {
        $validator = new AddressSchemaValidator();

        $result = $validator->validate('ParseRequest', [
            'text' => null,
            'countryHint' => 'GB',
        ]);

        self::assertSame(['ok' => false, 'error' => 'missing_text'], $result);
    }

    public function testValidateAcceptsValidPayload(): void
    {
        $validator = new AddressSchemaValidator();

        $result = $validator->validate('ParseRequest', [
            'text' => '221B Baker Street',
            'countryHint' => 'GB',
        ]);

        self::assertSame(['ok' => true], $result);
    }

    public function testValidateHandlesUnknownSchemaAndIgnoredOptionalData(): void
    {
        $validator = new AddressSchemaValidator();

        self::assertSame(
            ['ok' => false, 'error' => 'unknown_schema'],
            $validator->validate('UnknownRequest', []),
        );

        self::assertSame(
            ['ok' => true],
            $validator->validate('ParseRequest', [
                'text' => '221B Baker Street',
                'countryHint' => null,
                'ignored' => ['anything' => true],
            ]),
        );
    }

    public function testExpectedTypeHelperCoversAllSupportedPrimitiveKinds(): void
    {
        $validator = new AddressSchemaValidator();
        $method = new \ReflectionMethod(AddressSchemaValidator::class, 'isExpectedType');

        self::assertTrue($method->invoke($validator, 'value', 'string'));
        self::assertTrue($method->invoke($validator, 42, 'int'));
        self::assertTrue($method->invoke($validator, 4.2, 'float'));
        self::assertTrue($method->invoke($validator, true, 'bool'));
        self::assertTrue($method->invoke($validator, ['x'], 'array'));
        self::assertFalse($method->invoke($validator, 'value', 'unsupported'));
    }
}
