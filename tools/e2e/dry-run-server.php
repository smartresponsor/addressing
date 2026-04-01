<?php
declare(strict_types=1);

$command = [
    PHP_BINARY,
    '-S',
    '127.0.0.1:8000',
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
    if (!waitForUrl('http://127.0.0.1:8000/address/manage', 30)) {
        throw new RuntimeException('local_server_not_ready');
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
proc_close($process);

if (($status['exitcode'] ?? 0) > 0 && !($status['signaled'] ?? false)) {
    fwrite(STDERR, $stdout.$stderr);
    exit((int) $status['exitcode']);
}

fwrite(STDOUT, "Local server boot dry-run succeeded.\n");
