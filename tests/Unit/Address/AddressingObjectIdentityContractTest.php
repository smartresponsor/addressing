<?php

declare(strict_types=1);

namespace Tests\Unit\Address;

use App\Addressing\Entity\Address\AddressCountryEntity;
use App\Objecting\Embeddable\ObjectIdentityEmbeddable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class AddressingObjectIdentityContractTest extends TestCase
{
    public function testAddressCountryUsesCanonicalObjectIdentityContract(): void
    {
        $country = new AddressCountryEntity();
        $objectUuid = $country->getObjectUuid();

        self::assertSame(26, \strlen($objectUuid));
        self::assertInstanceOf(UuidV7::class, Uuid::fromString($objectUuid));
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $objectUuid);
        self::assertSame($objectUuid, $country->getObjectSlug());

        $country->setObjectSlug('united-states');

        self::assertSame($objectUuid, $country->getObjectUuid());
        self::assertSame('united-states', $country->getObjectSlug());
    }

    public function testAddressCountryReferenceFieldsRoundTrip(): void
    {
        $country = new AddressCountryEntity();

        self::assertSame('US', $country->getIso2());
        self::assertNull($country->getIso3());
        self::assertNull($country->getNumericCode());
        self::assertSame('', $country->getName());
        self::assertNull($country->getNativeName());
        self::assertNull($country->getPhoneCode());
        self::assertTrue($country->isEnabled());

        self::assertSame($country, $country->setIso2('CA'));
        self::assertSame($country, $country->setIso3('CAN'));
        self::assertSame($country, $country->setNumericCode('124'));
        self::assertSame($country, $country->setName('Canada'));
        self::assertSame($country, $country->setNativeName('Canada'));
        self::assertSame($country, $country->setPhoneCode('+1'));
        self::assertSame($country, $country->setEnabled(false));

        self::assertSame('CA', $country->getIso2());
        self::assertSame('CAN', $country->getIso3());
        self::assertSame('124', $country->getNumericCode());
        self::assertSame('Canada', $country->getName());
        self::assertSame('Canada', $country->getNativeName());
        self::assertSame('+1', $country->getPhoneCode());
        self::assertFalse($country->isEnabled());
    }

    public function testAddressCountryMappingUsesBinaryUuidAndMandatorySlug(): void
    {
        $configuration = ORMSetup::createAttributeMetadataConfiguration([], true);
        $configuration->enableNativeLazyObjects(true);
        $driverChain = new MappingDriverChain();

        $addressingDriver = ORMSetup::createAttributeMetadataConfiguration([
            dirname(__DIR__, 3).'/src/Entity',
        ], true)->getMetadataDriverImpl();
        self::assertNotNull($addressingDriver);
        $driverChain->addDriver($addressingDriver, 'App\Addressing\\Entity');

        $objectingDriver = ORMSetup::createAttributeMetadataConfiguration([
            dirname(__DIR__, 4).'/Objecting/src/Embeddable',
        ], true)->getMetadataDriverImpl();
        self::assertNotNull($objectingDriver);
        $driverChain->addDriver($objectingDriver, 'App\\Objecting\\Embeddable');
        $configuration->setMetadataDriverImpl($driverChain);

        $entityManager = new EntityManager(DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $configuration), $configuration);

        $metadata = $entityManager->getClassMetadata(AddressCountryEntity::class);
        $objectingMetadata = $entityManager->getClassMetadata(ObjectIdentityEmbeddable::class);

        self::assertTrue($objectingMetadata->isEmbeddedClass);
        self::assertSame('binary', $objectingMetadata->getFieldMapping('uuid')['type']);
        self::assertSame(16, $objectingMetadata->getFieldMapping('uuid')['length']);
        self::assertFalse($objectingMetadata->getFieldMapping('uuid')['nullable'] ?? false);
        self::assertSame('string', $objectingMetadata->getFieldMapping('slug')['type']);
        self::assertSame(190, $objectingMetadata->getFieldMapping('slug')['length']);
        self::assertFalse($objectingMetadata->getFieldMapping('slug')['nullable'] ?? false);

        $objectUuid = $metadata->getFieldMapping('objectIdentity.uuid');
        $objectSlug = $metadata->getFieldMapping('objectIdentity.slug');

        self::assertArrayHasKey('objectIdentity', $metadata->embeddedClasses);
        self::assertSame('uuid', $objectUuid['columnName']);
        self::assertSame('binary', $objectUuid['type']);
        self::assertSame(16, $objectUuid['length']);
        self::assertFalse($objectUuid['nullable'] ?? false);
        self::assertSame('slug', $objectSlug['columnName']);
        self::assertSame('string', $objectSlug['type']);
        self::assertSame(190, $objectSlug['length']);
        self::assertFalse($objectSlug['nullable'] ?? false);
    }
}
