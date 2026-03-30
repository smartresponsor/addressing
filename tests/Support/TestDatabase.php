<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Support;

use App\Integration\Persistence\AddressSchemaManager;
use PDO;

final class TestDatabase
{
    public static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function createPdo(): PDO
    {
        $dsn = getenv('TEST_DB_DSN');
        $user = getenv('TEST_DB_USER');
        $pass = getenv('TEST_DB_PASS');

        if (is_string($dsn) && $dsn !== '') {
            $pdo = new PDO($dsn, is_string($user) ? $user : null, is_string($pass) ? $pass : null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } else {
            $pdo = self::createInMemorySqlitePdo();
        }

        return $pdo;
    }

    public static function createInMemorySqlitePdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    public static function createSqlitePdo(string $sqlitePath): PDO
    {
        $pdo = new PDO('sqlite:'.$sqlitePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    public static function resetAddressSchema(PDO $pdo): void
    {
        AddressSchemaManager::resetSchema($pdo, self::projectRoot());
    }

    public static function freshSqlitePath(string $suffix): string
    {
        $path = self::projectRoot().'/var/'.preg_replace('/[^A-Za-z0-9_-]/', '-', $suffix).'.sqlite';
        if (is_file($path)) {
            unlink($path);
        }

        return $path;
    }
}
