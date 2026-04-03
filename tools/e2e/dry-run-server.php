<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/support/AddressRuntimeBootstrap.php';

if (!AddressRuntimeBootstrap::hasPdoDriver()) {
    fwrite(STDOUT, json_encode(
        AddressRuntimeBootstrap::blockedHostPayload('dry_run_server', 'no_pdo_driver_available_in_host_php'),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ).PHP_EOL);

    exit(2);
}

$port = random_int(18000, 19999);
$baseUrl = sprintf('http://127.0.0.1:%d', $port);

$command = [
    PHP_BINARY,
    '-S',
    sprintf('127.0.0.1:%d', $port),
    '-t',
    'public',
    'public/router.php',
];

$env = $_ENV;
$env['APP_ENV'] = 'test';
$env['APP_DEBUG'] = '0';
$env['ADDRESS_DB_DSN'] = $env['ADDRESS_DB_DSN'] ?? 'sqlite:'.dirname(__DIR__, 2).'/var/addressing-dry-run.sqlite';

$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__, 2), $env);
if (!is_resource($process)) {
    fwrite(STDERR, "Failed to start local server.\n");
    exit(1);
}

try {
    require __DIR__.'/wait-for-url.php';
    if (!waitForUrl($baseUrl.'/address/manage', 30)) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fwrite(STDOUT, json_encode([
            'component' => 'Addressing',
            'check' => 'dry_run_server',
            'status' => 'failed',
            'reason' => 'local_server_not_ready',
            'baseUrl' => $baseUrl,
            'stdout' => $stdout === false ? '' : $stdout,
            'stderr' => $stderr === false ? '' : $stderr,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        exit(1);
    }
} catch (Throwable $exception) {
    foreach ($pipes as $pipe) {
        fclose($pipe);
    }
    proc_terminate($process);
    proc_close($process);
    throw $exception;
}

fclose($pipes[0]);
proc_terminate($process);
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$status = proc_get_status($process);
$exitCode = (int) $status['exitcode'];
$signaled = $status['signaled'];
proc_close($process);

if ($exitCode > 0 && !$signaled) {
    fwrite(STDOUT, json_encode([
        'component' => 'Addressing',
        'check' => 'dry_run_server',
        'status' => 'failed',
        'reason' => 'local_server_exit_non_zero',
        'baseUrl' => $baseUrl,
        'exitCode' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    exit($exitCode);
}

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'dry_run_server',
    'status' => 'ready',
    'baseUrl' => $baseUrl,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
