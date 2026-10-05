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

    public function testCountryCodeValueAndInvalidInput(): void
    {
        $countryCode = new AddressCountryCode(' ca ');

        self::assertSame('CA', $countryCode->value());
        self::assertFalse($countryCode->equals(new AddressCountryCode('US')));

        foreach (['', 'U', 'USA', '1A'] as $invalid) {
            try {
                new AddressCountryCode($invalid);
                self::fail('Expected invalid country code exception.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('CountryCode must be ISO 3166-1 alpha-2', $exception->getMessage());
            }
        }
    }

    public function testPostalCodeValueEqualityAndBounds(): void
    {
        $postalCode = new AddressPostalCode(' 77002 ');

        self::assertSame('77002', $postalCode->value());
        self::assertTrue($postalCode->equals(new AddressPostalCode('77002')));
        self::assertFalse($postalCode->equals(new AddressPostalCode('77003')));

        foreach (['', '12', str_repeat('1', 33)] as $invalid) {
            try {
                new AddressPostalCode($invalid);
                self::fail('Expected invalid postal code exception.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testStreetLineValueEqualityAndBounds(): void
    {
        $streetLine = new AddressStreetLine(' 123 Main St ');

        self::assertSame('123 Main St', $streetLine->value());
        self::assertTrue($streetLine->equals(new AddressStreetLine('123 Main St')));
        self::assertFalse($streetLine->equals(new AddressStreetLine('124 Main St')));

        foreach (['', 'A', str_repeat('x', 257)] as $invalid) {
            try {
                new AddressStreetLine($invalid);
                self::fail('Expected invalid street line exception.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testSubdivisionCodeEqualityAndBounds(): void
    {
        $subdivision = new AddressSubdivision(' tx ');

        self::assertSame('TX', $subdivision->code());
        self::assertSame('TX', (string) $subdivision);
        self::assertFalse($subdivision->equals(new AddressSubdivision('CA')));

        foreach (['', str_repeat('x', 33)] as $invalid) {
            try {
                new AddressSubdivision($invalid);
                self::fail('Expected invalid subdivision exception.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('Subdivision code is invalid', $exception->getMessage());
            }
        }
    }

    public function testGeoPointAccessorsKeyInequalityAndBounds(): void
    {
        $point = new AddressGeoPoint(29.7604, -95.3698);

        self::assertSame(29.7604, $point->lat());
        self::assertSame(-95.3698, $point->lon());
        self::assertSame('+29.760400,-95.369800', $point->toKey());
        self::assertFalse($point->equals(new AddressGeoPoint(29.7605, -95.3698)));

        foreach ([[-90.1, 0.0], [90.1, 0.0], [0.0, -180.1], [0.0, 180.1]] as [$lat, $lon]) {
            try {
                new AddressGeoPoint($lat, $lon);
                self::fail('Expected invalid geo point exception.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
