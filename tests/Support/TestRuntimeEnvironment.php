<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Support;

use Symfony\Component\Filesystem\Filesystem;

final class TestRuntimeEnvironment
{
    private static ?string $runtimeVarDir = null;

    public static function configureSqliteAddressRuntime(string $sqlitePath): void
    {
        $dsn = 'sqlite:'.$sqlitePath;

        putenv('ADDRESS_DB_PATH='.$sqlitePath);
        $_ENV['ADDRESS_DB_PATH'] = $sqlitePath;
        $_SERVER['ADDRESS_DB_PATH'] = $sqlitePath;
        putenv('ADDRESS_DB_DSN='.$dsn);
        $_ENV['ADDRESS_DB_DSN'] = $dsn;
        $_SERVER['ADDRESS_DB_DSN'] = $dsn;
        $runtimeVarDir = dirname($sqlitePath).'/runtime-'.sha1($sqlitePath.'|'.(string) hrtime(true));
        if (!is_dir($runtimeVarDir)) {
            mkdir($runtimeVarDir, 0777, true);
        }
        self::$runtimeVarDir = $runtimeVarDir;

        putenv('APP_VAR_DIR='.$runtimeVarDir);
        $_ENV['APP_VAR_DIR'] = $runtimeVarDir;
        $_SERVER['APP_VAR_DIR'] = $runtimeVarDir;
        $_SERVER['APP_ENV'] = 'test';
        $_SERVER['APP_DEBUG'] = '0';
    }

    public static function clearSqliteAddressRuntime(): void
    {
        putenv('ADDRESS_DB_PATH');
        unset($_ENV['ADDRESS_DB_PATH'], $_SERVER['ADDRESS_DB_PATH']);
        putenv('ADDRESS_DB_DSN');
        unset($_ENV['ADDRESS_DB_DSN'], $_SERVER['ADDRESS_DB_DSN']);
        putenv('APP_VAR_DIR');
        unset($_ENV['APP_VAR_DIR'], $_SERVER['APP_VAR_DIR']);
        unset($_SERVER['APP_ENV'], $_SERVER['APP_DEBUG']);

        if (null !== self::$runtimeVarDir && is_dir(self::$runtimeVarDir)) {
            (new Filesystem())->remove(self::$runtimeVarDir);
        }

        self::$runtimeVarDir = null;
    }
}
