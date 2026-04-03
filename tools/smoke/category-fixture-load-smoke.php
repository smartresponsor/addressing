<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp

declare(strict_types=1);

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

require_once dirname(__DIR__).'/support/AddressRuntimeBootstrap.php';

if (!AddressRuntimeBootstrap::hasPdoDriver()) {
    fwrite(STDOUT, json_encode(
        AddressRuntimeBootstrap::blockedHostPayload('fixture_load', 'no_pdo_driver_available_in_host_php'),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ).PHP_EOL);

    exit(2);
}

$count = isset($argv[1]) && is_numeric($argv[1]) ? max(1, (int) $argv[1]) : 1;
$application = AddressRuntimeBootstrap::consoleApplication();
$command = $application->find('address:demo:load');
$input = new ArrayInput([
    'command' => 'address:demo:load',
    '--count' => (string) $count,
]);
$input->setInteractive(false);
$output = new BufferedOutput();
$exitCode = $command->run($input, $output);
$commandOutput = $output->fetch();

$pdo = AddressRuntimeBootstrap::pdo();
$rowCountStatement = $pdo->query('SELECT COUNT(*) FROM address_entity');
if (!$rowCountStatement instanceof \PDOStatement) {
    throw new RuntimeException('fixture_load_row_count_query_failed');
}
$rowCount = (int) $rowCountStatement->fetchColumn();
$ok = $exitCode === 0 && $rowCount === $count;

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'fixture_load',
    'status' => $ok ? 'ready' : 'incomplete',
    'requested' => $count,
    'exitCode' => $exitCode,
    'rowCount' => $rowCount,
    'output' => $commandOutput,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if (!$ok) {
    throw new RuntimeException('fixture_load_smoke_failed');
}
