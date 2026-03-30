<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Http\Controller\AddressController;
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

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

$formFactory = $container->get(FormFactoryInterface::class);
$twig = $container->get(Environment::class);
$controller = $container->get(AddressController::class);

if (!$formFactory instanceof FormFactoryInterface) {
    throw new RuntimeException('form_factory_not_available');
}

if (!$twig instanceof Environment) {
    throw new RuntimeException('twig_not_available');
}

if (!$controller instanceof AddressController) {
    throw new RuntimeException('address_controller_not_available');
}

fwrite(STDOUT, "container boot smoke ok\n");
