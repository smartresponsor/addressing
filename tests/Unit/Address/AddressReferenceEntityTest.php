<?php

declare(strict_types=1);

namespace Tests\Unit\Address;

use App\Addressing\Entity\Address\AddressCityEntity;
use App\Addressing\Entity\Address\AddressComponentEntity;
use App\Addressing\Entity\Address\AddressFormatEntity;
use App\Addressing\Entity\Address\AddressPostalCodeEntity;
use App\Addressing\Entity\Address\AddressProvinceEntity;
use App\Addressing\Entity\Address\AddressStreetEntity;
use App\Addressing\Entity\Address\AddressStreetTypeEntity;
use PHPUnit\Framework\TestCase;

final class AddressReferenceEntityTest extends TestCase
{
    public function testCityReferenceRoundTripsAddressingOwnedFields(): void
    {
        $entity = (new AddressCityEntity())
            ->setCountryCode('CA')
            ->setProvinceCode('ON')
            ->setName('Toronto')
            ->setNormalizedName('toronto')
            ->setTimezone('America/Toronto')
            ->setEnabled(false);

        self::assertSame('CA', $entity->getCountryCode());
        self::assertSame('ON', $entity->getProvinceCode());
        self::assertSame('Toronto', $entity->getName());
        self::assertSame('toronto', $entity->getNormalizedName());
        self::assertSame('America/Toronto', $entity->getTimezone());
        self::assertFalse($entity->isEnabled());
    }

    public function testComponentReferenceRoundTripsNormalizationEvidence(): void
    {
        $entity = (new AddressComponentEntity())
            ->setAddressId('01J00000000000000000000000')
            ->setComponentType('locality')
            ->setComponentValue('Toronto')
            ->setNormalizedValue('toronto')
            ->setSource('provider-a')
            ->setConfidence(0.98);

        self::assertSame('01J00000000000000000000000', $entity->getAddressId());
        self::assertSame('locality', $entity->getComponentType());
        self::assertSame('Toronto', $entity->getComponentValue());
        self::assertSame('toronto', $entity->getNormalizedValue());
        self::assertSame('provider-a', $entity->getSource());
        self::assertSame(0.98, $entity->getConfidence());
    }

    public function testFormatReferenceRoundTripsCountryFormattingPolicy(): void
    {
        $required = ['line1' => true, 'city' => true];
        $allowed = ['region' => true, 'postalCode' => true];
        $entity = (new AddressFormatEntity())
            ->setCountryCode('CA')
            ->setFormatCode('postal')
            ->setDisplayPattern('{line1}, {city} {region} {postalCode}')
            ->setRequiredFields($required)
            ->setAllowedFields($allowed)
            ->setPostalCodePattern('^[A-Z]\\d[A-Z] ?\\d[A-Z]\\d$')
            ->setExample('100 King St W, Toronto ON M5X 1A9');

        self::assertSame('CA', $entity->getCountryCode());
        self::assertSame('postal', $entity->getFormatCode());
        self::assertSame('{line1}, {city} {region} {postalCode}', $entity->getDisplayPattern());
        self::assertSame($required, $entity->getRequiredFields());
        self::assertSame($allowed, $entity->getAllowedFields());
        self::assertSame('^[A-Z]\\d[A-Z] ?\\d[A-Z]\\d$', $entity->getPostalCodePattern());
        self::assertSame('100 King St W, Toronto ON M5X 1A9', $entity->getExample());
    }

    public function testPostalReferenceRoundTripsLocationData(): void
    {
        $entity = (new AddressPostalCodeEntity())
            ->setCountryCode('CA')
            ->setPostalCode('M5X 1A9')
            ->setProvinceCode('ON')
            ->setCityName('Toronto')
            ->setLatitude(43.6487)
            ->setLongitude(-79.3817);

        self::assertSame('CA', $entity->getCountryCode());
        self::assertSame('M5X 1A9', $entity->getPostalCode());
        self::assertSame('ON', $entity->getProvinceCode());
        self::assertSame('Toronto', $entity->getCityName());
        self::assertSame(43.6487, $entity->getLatitude());
        self::assertSame(-79.3817, $entity->getLongitude());
    }

    public function testProvinceReferenceRoundTripsAdministrativeArea(): void
    {
        $entity = (new AddressProvinceEntity())
            ->setCountryCode('CA')
            ->setCode('ON')
            ->setName('Ontario')
            ->setType('province')
            ->setEnabled(false);

        self::assertSame('CA', $entity->getCountryCode());
        self::assertSame('ON', $entity->getCode());
        self::assertSame('Ontario', $entity->getName());
        self::assertSame('province', $entity->getType());
        self::assertFalse($entity->isEnabled());
    }

    public function testStreetReferenceRoundTripsCanonicalStreetData(): void
    {
        $entity = (new AddressStreetEntity())
            ->setCountryCode('CA')
            ->setProvinceCode('ON')
            ->setCityName('Toronto')
            ->setStreetName('King')
            ->setStreetType('Street')
            ->setNormalizedName('king street');

        self::assertSame('CA', $entity->getCountryCode());
        self::assertSame('ON', $entity->getProvinceCode());
        self::assertSame('Toronto', $entity->getCityName());
        self::assertSame('King', $entity->getStreetName());
        self::assertSame('Street', $entity->getStreetType());
        self::assertSame('king street', $entity->getNormalizedName());
    }

    public function testStreetTypeReferenceRoundTripsVocabulary(): void
    {
        $entity = (new AddressStreetTypeEntity())
            ->setCountryCode('CA')
            ->setCode('ST')
            ->setLabel('Street')
            ->setAbbreviation('St')
            ->setSortOrder(10);

        self::assertSame('CA', $entity->getCountryCode());
        self::assertSame('ST', $entity->getCode());
        self::assertSame('Street', $entity->getLabel());
        self::assertSame('St', $entity->getAbbreviation());
        self::assertSame(10, $entity->getSortOrder());
    }
}
