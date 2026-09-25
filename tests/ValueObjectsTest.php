<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests;

use App\Addressing\Value\AddressCountryCode;
use App\Addressing\Value\AddressGeoPoint;
use App\Addressing\Value\AddressPostalCode;
use App\Addressing\Value\AddressStreetLine;
use App\Addressing\Value\AddressSubdivision;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function testCountryCode(): void
    {
        $a = new AddressCountryCode('us');
        $b = new AddressCountryCode('US');
        self::assertTrue($a->equals($b));
        self::assertSame('US', (string) $a);
    }

    public function testSubdivision(): void
    {
        $a = new AddressSubdivision('tx');
        $b = new AddressSubdivision('TX');
        self::assertTrue($a->equals($b));
    }

    public function testPostalCode(): void
    {
        $a = new AddressPostalCode('770 02');
        self::assertSame('770 02', (string) $a);
    }

    public function testStreetLine(): void
    {
        $a = new AddressStreetLine('123 Main St.');
        $b = new AddressStreetLine('123 Main St.');
        self::assertTrue($a->equals($b));
    }

    public function testGeoPoint(): void
    {
        $a = new AddressGeoPoint(29.7604, -95.3698);
        $b = new AddressGeoPoint(29.7604, -95.3698);
        self::assertTrue($a->equals($b));
        self::assertStringContainsString('29.760400', (string) $a);
    }
}
