<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

require_once dirname(__DIR__).'/support/AddressRuntimeBootstrap.php';

if (!AddressRuntimeBootstrap::hasPdoDriver()) {
    fwrite(STDOUT, json_encode(
        AddressRuntimeBootstrap::blockedHostPayload('fixture_sanity', 'no_pdo_driver_available_in_host_php'),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ).PHP_EOL);

    exit(2);
}

$application = AddressRuntimeBootstrap::consoleApplication();
$hasCommand = $application->has('address:demo:load');

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'fixture_sanity',
    'status' => $hasCommand ? 'ready' : 'incomplete',
    'commands' => [
        'address:demo:load' => $hasCommand,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if (!$hasCommand) {
    throw new RuntimeException('fixture_sanity_smoke_failed');
}
