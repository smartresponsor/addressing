<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Support;

final class TestRuntimeEnvironment
{
    public static function configureSqliteAddressRuntime(string $sqlitePath): void
    {
        $dsn = 'sqlite:'.$sqlitePath;

        putenv('ADDRESS_DB_DSN='.$dsn);
        $_ENV['ADDRESS_DB_DSN'] = $dsn;
        $_SERVER['ADDRESS_DB_DSN'] = $dsn;
        $_SERVER['APP_ENV'] = 'test';
        $_SERVER['APP_DEBUG'] = '0';
    }

    public static function clearSqliteAddressRuntime(): void
    {
        putenv('ADDRESS_DB_DSN');
        unset($_ENV['ADDRESS_DB_DSN'], $_SERVER['ADDRESS_DB_DSN']);
        unset($_SERVER['APP_ENV'], $_SERVER['APP_DEBUG']);
    }
}
