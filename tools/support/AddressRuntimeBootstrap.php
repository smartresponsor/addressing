<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Kernel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';

final class AddressRuntimeBootstrap
{
    private static ?Kernel $kernel = null;

    public static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function bootKernel(): Kernel
    {
        if (self::$kernel instanceof Kernel) {
            return self::$kernel;
        }

        $root = self::projectRoot();
        if (class_exists(Dotenv::class) && is_file($root.'/.env')) {
            (new Dotenv())->bootEnv($root.'/.env');
        }

        $_SERVER['APP_ENV'] ??= 'dev';
        $_SERVER['APP_DEBUG'] ??= '1';

        self::$kernel = new Kernel((string) $_SERVER['APP_ENV'], self::debugFlag());
        self::$kernel->boot();

        return self::$kernel;
    }

    public static function container(): ContainerInterface
    {
        return self::bootKernel()->getContainer();
    }

    public static function service(string $id): mixed
    {
        return self::container()->get($id);
    }

    public static function pdo(): \PDO
    {
        $pdo = self::service(\PDO::class);
        if (!$pdo instanceof \PDO) {
            throw new RuntimeException('primary_pdo_service_missing');
        }

        return $pdo;
    }

    private static function debugFlag(): bool
    {
        $value = $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG');
        $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $normalized ?? false;
    }
}
