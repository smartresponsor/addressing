<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Value\CountryCode;
use App\Value\Subdivision;
use App\Value\PostalCode;
use App\Value\StreetLine;
use App\Value\GeoPoint;

/**
 *
 */

/**
 *
 */
final class ValueObjectsTest extends TestCase
{
    /**
     * @return void
     */
    public function testCountryCode(): void
    {
        $a = new CountryCode('us');
        $b = new CountryCode('US');
        ValueObjectsTest::assertTrue($a->equals($b));
        ValueObjectsTest::assertSame('US', (string)$a);
    }

    /**
     * @return void
     */
    public function testSubdivision(): void
    {
        $a = new Subdivision('tx');
        $b = new Subdivision('TX');
        ValueObjectsTest::assertTrue($a->equals($b));
    }

    /**
     * @return void
     */
    public function testPostalCode(): void
    {
        $a = new PostalCode('770 02');
        ValueObjectsTest::assertSame('770 02', (string)$a);
    }

    /**
     * @return void
     */
    public function testStreetLine(): void
    {
        $a = new StreetLine('123 Main St.');
        $b = new StreetLine('123 Main St.');
        ValueObjectsTest::assertTrue($a->equals($b));
    }

    /**
     * @return void
     */
    public function testGeoPoint(): void
    {
        $a = new GeoPoint(29.7604, -95.3698);
        $b = new GeoPoint(29.7604, -95.3698);
        ValueObjectsTest::assertTrue($a->equals($b));
        ValueObjectsTest::assertStringContainsString('29.760400', (string)$a);
    }
}
