<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Addressing\Value\AddressCountryCode;
use App\Addressing\Value\AddressPostalCode;
use App\Addressing\Value\AddressStreetLine;
use App\Addressing\Value\AddressSubdivision;
use App\Addressing\Value\Primitive\AddressRegion;
use PHPUnit\Framework\TestCase;

final class ValueEqualityCoverageTest extends TestCase
{
    public function testValueObjectEqualityCoversEqualAndDifferentInstances(): void
    {
        $country = new AddressCountryCode('us');
        self::assertTrue($country->equals(new AddressCountryCode('US')));
        self::assertFalse($country->equals(new AddressCountryCode('CA')));

        $postal = new AddressPostalCode('77002');
        self::assertTrue($postal->equals(new AddressPostalCode('77002')));
        self::assertFalse($postal->equals(new AddressPostalCode('10001')));

        $street = new AddressStreetLine('Main St');
        self::assertTrue($street->equals(new AddressStreetLine('Main St')));
        self::assertFalse($street->equals(new AddressStreetLine('Other St')));

        $subdivision = new AddressSubdivision('tx');
        self::assertTrue($subdivision->equals(new AddressSubdivision('TX')));
        self::assertFalse($subdivision->equals(new AddressSubdivision('CA')));
    }

    public function testPrimitiveRegionStringifiesNormalizedInput(): void
    {
        self::assertSame('TX', (string) new AddressRegion(' tx '));
    }
}
