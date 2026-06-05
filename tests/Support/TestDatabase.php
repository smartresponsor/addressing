<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Support;

use App\Entity\AddressEntity;
use App\Entity\AddressEvidenceSnapshotEntity;
use App\Entity\AddressOutboxEntity;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

final class TestDatabase
{
    public static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function createConnection(): Connection
    {
        $dsn = getenv('TEST_DB_DSN');
        $user = getenv('TEST_DB_USER');
        $pass = getenv('TEST_DB_PASS');

        if (is_string($dsn) && '' !== $dsn) {
            $params = ['url' => $dsn];
            if (is_string($user) && '' !== $user) {
                $params['user'] = $user;
            }
            if (is_string($pass) && '' !== $pass) {
                $params['password'] = $pass;
            }

            return DriverManager::getConnection($params);
        }

        return self::createInMemorySqliteConnection();
    }

    public static function createInMemorySqliteConnection(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    public static function createSqliteConnection(string $sqlitePath): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $sqlitePath]);
    }

    /**
     * @param list<class-string> $classes
     */
    public static function createInMemoryEntityManager(array $classes = []): EntityManagerInterface
    {
        $entityManager = self::createEntityManager(self::createInMemorySqliteConnection());
        if ([] !== $classes) {
            self::resetEntitySchema($entityManager, $classes);
        }

        return $entityManager;
    }

    /**
     * @param list<class-string> $classes
     */
    public static function createSqliteEntityManager(string $sqlitePath, array $classes = []): EntityManagerInterface
    {
        $entityManager = self::createEntityManager(self::createSqliteConnection($sqlitePath));
        if ([] !== $classes) {
            self::resetEntitySchema($entityManager, $classes);
        }

        return $entityManager;
    }

    public static function resetAddressSchema(Connection $connection): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([
            self::projectRoot().'/src/Entity',
        ], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager($connection, $config);
        self::resetEntitySchema($entityManager, [
            AddressEntity::class,
            AddressEvidenceSnapshotEntity::class,
            AddressOutboxEntity::class,
        ]);
        $entityManager->close();
    }

    public static function freshSqlitePath(string $suffix): string
    {
        $path = self::projectRoot().'/var/'.preg_replace('/[^A-Za-z0-9_-]/', '-', $suffix).'.sqlite';
        if (is_file($path)) {
            unlink($path);
        }

        return $path;
    }

    /**
     * @param list<class-string> $classes
     */
    private static function resetEntitySchema(EntityManagerInterface $entityManager, array $classes): void
    {
        $tool = new SchemaTool($entityManager);
        $metadata = array_map(
            static fn (string $class): \Doctrine\ORM\Mapping\ClassMetadata => $entityManager->getClassMetadata($class),
            $classes,
        );

        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    private static function createEntityManager(Connection $connection): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([
            self::projectRoot().'/src/Entity',
        ], true);
        $config->enableNativeLazyObjects(true);

        return new EntityManager($connection, $config);
    }
}
