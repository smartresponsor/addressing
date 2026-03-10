<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Util\Address\AddressUlid;
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
}
