<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Http\Controller\AddressController;
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../../vendor/autoload.php';

if (class_exists(Dotenv::class) && file_exists(__DIR__.'/../../.env')) {
    (new Dotenv())->bootEnv(__DIR__.'/../../.env');
}

$_SERVER['APP_ENV'] ??= 'dev';
$_SERVER['APP_DEBUG'] ??= '1';

$debug = filter_var((string) $_SERVER['APP_DEBUG'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
$kernel = new Kernel(
    (string) $_SERVER['APP_ENV'],
    null === $debug ? '1' === (string) $_SERVER['APP_DEBUG'] : $debug,
);
$kernel->boot();

$controller = $kernel->getContainer()->get(AddressController::class);
if (!$controller instanceof AddressController) {
    throw new RuntimeException('address_controller_not_available');
}

fwrite(STDOUT, "runtime smoke ok\n");
