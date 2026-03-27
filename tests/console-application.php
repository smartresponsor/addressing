<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

// Managed by Commanding inspection

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

if (class_exists(Dotenv::class) && file_exists(__DIR__.'/../.env')) {
    (new Dotenv())->bootEnv(__DIR__.'/../.env');
}

$_SERVER['APP_ENV'] ??= 'dev';
$_SERVER['APP_DEBUG'] ??= '1';

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);

return new Application($kernel);
