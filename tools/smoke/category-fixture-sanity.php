<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Fixture\AddressDemoFixtureService;
use App\Integration\Console\Command\AddressDemoLoadCommand;
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
$container = $kernel->getContainer();

$fixtureService = $container->get(AddressDemoFixtureService::class);
$demoLoadCommand = $container->get(AddressDemoLoadCommand::class);

if (!$fixtureService instanceof AddressDemoFixtureService) {
    throw new RuntimeException('fixture_service_not_available');
}

if (!$demoLoadCommand instanceof AddressDemoLoadCommand) {
    throw new RuntimeException('fixture_command_not_available');
}

fwrite(STDOUT, "fixture sanity ok\n");
