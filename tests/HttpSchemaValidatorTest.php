<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests;

use App\Http\Schema\Validator;
use PHPUnit\Framework\TestCase;

final class HttpSchemaValidatorTest extends TestCase
{
    public function testValidateRejectsWrongType(): void
    {
        $validator = new Validator();

        $result = $validator->validate('ParseRequest', [
            'text' => '221B Baker Street',
            'countryHint' => 100,
        ]);

        self::assertSame(['ok' => false, 'error' => 'type_countryHint'], $result);
    }

    public function testValidateAcceptsValidPayload(): void
    {
        $validator = new Validator();

        $result = $validator->validate('ParseRequest', [
            'text' => '221B Baker Street',
            'countryHint' => 'GB',
        ]);

        self::assertSame(['ok' => true], $result);
    }
}
