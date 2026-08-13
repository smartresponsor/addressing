<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests;

use App\Addressing\Value\CountryCode;
use App\Addressing\Value\GeoPoint;
use App\Addressing\Value\PostalCode;
use App\Addressing\Value\StreetLine;
use App\Addressing\Value\Subdivision;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function testCountryCode(): void
    {
        $a = new CountryCode('us');
        $b = new CountryCode('US');
        self::assertTrue($a->equals($b));
        self::assertSame('US', (string) $a);
    }

    public function testSubdivision(): void
    {
        $a = new Subdivision('tx');
        $b = new Subdivision('TX');
        self::assertTrue($a->equals($b));
    }

    public function testPostalCode(): void
    {
        $a = new PostalCode('770 02');
        self::assertSame('770 02', (string) $a);
    }

    public function testStreetLine(): void
    {
        $a = new StreetLine('123 Main St.');
        $b = new StreetLine('123 Main St.');
        self::assertTrue($a->equals($b));
    }

    public function testGeoPoint(): void
    {
        $a = new GeoPoint(29.7604, -95.3698);
        $b = new GeoPoint(29.7604, -95.3698);
        self::assertTrue($a->equals($b));
        self::assertStringContainsString('29.760400', (string) $a);
    }
}
