<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests;

use App\Addressing\Factory\Identifier\AddressUlid;
use PHPUnit\Framework\TestCase;

final class AddressUlidTest extends TestCase
{
    public function testGenerateReturnsUlidCompatibleToken(): void
    {
        $ulid = AddressUlid::generate();

        self::assertSame(26, strlen($ulid));
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ulid);
    }

    public function testGenerateProducesDifferentValues(): void
    {
        $first = AddressUlid::generate();
        $second = AddressUlid::generate();

        self::assertNotSame($first, $second);
    }

    public function testEncodingHelpersCoverZeroIntegerAndBinaryEntropy(): void
    {
        $intEncoder = new \ReflectionMethod(AddressUlid::class, 'base32FromInt');
        self::assertSame('0000000000', $intEncoder->invoke(null, 0));
        self::assertSame('0000000001', $intEncoder->invoke(null, 1));
        self::assertSame('0000000010', $intEncoder->invoke(null, 32));

        $binaryEncoder = new \ReflectionMethod(AddressUlid::class, 'base32FromBinary');
        $encoded = $binaryEncoder->invoke(null, str_repeat("\0", 10));
        self::assertIsString($encoded);
        self::assertSame(16, strlen($encoded));
        self::assertSame(str_repeat('0', 16), $encoded);
    }
}
