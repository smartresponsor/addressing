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
        self::assertSame('binary', $objectingMetadata->getFieldMapping('objectUuid')['type']);
        self::assertSame(16, $objectingMetadata->getFieldMapping('objectUuid')['length']);
        self::assertFalse($objectingMetadata->getFieldMapping('objectUuid')['nullable'] ?? false);
        self::assertSame('string', $objectingMetadata->getFieldMapping('objectSlug')['type']);
        self::assertSame(190, $objectingMetadata->getFieldMapping('objectSlug')['length']);
        self::assertFalse($objectingMetadata->getFieldMapping('objectSlug')['nullable'] ?? false);

        $objectUuid = $metadata->getFieldMapping('objectIdentity.objectUuid');
        $objectSlug = $metadata->getFieldMapping('objectIdentity.objectSlug');

        self::assertArrayHasKey('objectIdentity', $metadata->embeddedClasses);
        self::assertSame('object_uuid', $objectUuid['columnName']);
        self::assertSame('binary', $objectUuid['type']);
        self::assertSame(16, $objectUuid['length']);
        self::assertFalse($objectUuid['nullable'] ?? false);
        self::assertSame('object_slug', $objectSlug['columnName']);
        self::assertSame('string', $objectSlug['type']);
        self::assertSame(190, $objectSlug['length']);
        self::assertFalse($objectSlug['nullable'] ?? false);
    }
}
