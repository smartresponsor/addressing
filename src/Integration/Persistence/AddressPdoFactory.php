<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Integration\Persistence;

final class AddressPdoFactory
{
    public static function createPrimary(): \PDO
    {
        $dsn = self::env('ADDRESS_DB_DSN')
            ?? self::env('PG_DSN')
            ?? self::env('DB_DSN')
            ?? self::defaultSqliteDsn();

        $user = self::env('ADDRESS_DB_USER') ?? self::env('PG_USER');
        $pass = self::env('ADDRESS_DB_PASS') ?? self::env('PG_PASS');

        self::ensureVarDirectory();

        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        AddressSchemaManager::ensureSchema($pdo, self::projectDir());

        return $pdo;
    }

    public static function createRateLimit(): \PDO
    {
        $dsn = self::env('ADDRESS_RATE_LIMIT_DSN');
        if (null === $dsn) {
            self::ensureVarDirectory();
            $path = self::projectDir().'/var/addressing-rate-limit.sqlite';
            if (!is_writable(dirname($path))) {
                $path = sys_get_temp_dir().'/addressing-rate-limit.sqlite';
            }
            $dsn = 'sqlite:'.$path;
        }

        $pdo = new \PDO($dsn);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private static function env(string $key): ?string
    {
        $value = getenv($key);
        if (false === $value) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    private static function defaultSqliteDsn(): string
    {
        return 'sqlite:'.self::projectDir().'/var/addressing-demo.sqlite';
    }

    private static function ensureVarDirectory(): void
    {
        $path = self::projectDir().'/var';
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException('failed_to_create_var_directory');
        }
    }

    private static function projectDir(): string
    {
        return dirname(__DIR__, 3);
    }
}
