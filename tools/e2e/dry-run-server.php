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

/**
 * @return list<string>
 */
/**
 * @return list<array{bindHost: string, requestHost: string}>
 */
function dryRunHosts(): array
{
    return [
        ['bindHost' => '127.0.0.1', 'requestHost' => '127.0.0.1'],
        ['bindHost' => '0.0.0.0', 'requestHost' => '127.0.0.1'],
        ['bindHost' => 'localhost', 'requestHost' => 'localhost'],
    ];
}

function reserveLoopbackPort(string $bindHost): ?int
{
    $server = @stream_socket_server(sprintf('tcp://%s:0', $bindHost), $errno, $errstr);
    if ($server === false) {
        return null;
    }

    $name = stream_socket_get_name($server, false);
    fclose($server);
    if (!is_string($name) || $name === '') {
        return null;
    }

    $parts = explode(':', $name);
    $port = array_pop($parts);

    return is_string($port) && ctype_digit($port) ? (int) $port : null;
}

/**
 * @param array<string, string> $env
 * @return array{status: string, exitCode: int, payload: array<string, mixed>}
 */
function tryDryRunServer(string $bindHost, string $requestHost, int $port, array $env): array
{
    $baseUrl = sprintf('http://%s:%d', $requestHost, $port);
    $command = [
        PHP_BINARY,
        '-S',
        sprintf('%s:%d', $bindHost, $port),
        '-t',
        'public',
        'public/router.php',
    ];

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__, 2), $env);
    if (!is_resource($process)) {
        return [
            'status' => 'failed',
            'exitCode' => 1,
            'payload' => [
                'component' => 'Addressing',
                'check' => 'dry_run_server',
                'status' => 'failed',
                'reason' => 'local_server_process_start_failed',
                'baseUrl' => $baseUrl,
                'bindHost' => $bindHost,
                'host' => $requestHost,
                'port' => $port,
            ],
        ];
    }

    try {
        require_once __DIR__.'/wait-for-url.php';
        if (!waitForUrl($baseUrl.'/address/manage', 15)) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_terminate($process);
            proc_close($process);

            $stderrText = $stderr === false ? '' : $stderr;
            $bindFailure = str_contains($stderrText, 'Failed to listen on');

            return [
                'status' => $bindFailure ? 'blocked' : 'failed',
                'exitCode' => $bindFailure ? 2 : 1,
                'payload' => [
                    'component' => 'Addressing',
                    'check' => 'dry_run_server',
                    'status' => $bindFailure ? 'blocked' : 'failed',
                    'reason' => $bindFailure ? 'local_server_bind_failed' : 'local_server_not_ready',
                    'baseUrl' => $baseUrl,
                    'bindHost' => $bindHost,
                'host' => $requestHost,
                    'port' => $port,
                    'stdout' => $stdout === false ? '' : $stdout,
                    'stderr' => $stderrText,
                ],
            ];
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
    $signaled = (bool) $status['signaled'];
    proc_close($process);

    if ($exitCode > 0 && !$signaled) {
        return [
            'status' => 'failed',
            'exitCode' => $exitCode,
            'payload' => [
                'component' => 'Addressing',
                'check' => 'dry_run_server',
                'status' => 'failed',
                'reason' => 'local_server_exit_non_zero',
                'baseUrl' => $baseUrl,
                'bindHost' => $bindHost,
                'host' => $requestHost,
                'port' => $port,
                'exitCode' => $exitCode,
                'stdout' => $stdout === false ? '' : $stdout,
                'stderr' => $stderr === false ? '' : $stderr,
            ],
        ];
    }

    return [
        'status' => 'ready',
        'exitCode' => 0,
        'payload' => [
            'component' => 'Addressing',
            'check' => 'dry_run_server',
            'status' => 'ready',
            'baseUrl' => $baseUrl,
            'bindHost' => $bindHost,
            'host' => $requestHost,
            'port' => $port,
        ],
    ];
}

$env = $_ENV;
$env['APP_ENV'] = 'test';
$env['APP_DEBUG'] = '0';
$env['ADDRESS_DB_DSN'] = $env['ADDRESS_DB_DSN'] ?? 'sqlite:'.dirname(__DIR__, 2).'/var/addressing-dry-run.sqlite';

$attempts = [];
foreach (dryRunHosts() as $candidate) {
    $bindHost = $candidate['bindHost'];
    $requestHost = $candidate['requestHost'];

    $port = reserveLoopbackPort($bindHost);
    if ($port === null) {
        $attempts[] = [
            'bindHost' => $bindHost,
            'host' => $requestHost,
            'status' => 'blocked',
            'reason' => 'unable_to_reserve_loopback_port',
        ];
        continue;
    }

    $result = tryDryRunServer($bindHost, $requestHost, $port, $env);
    if ($result['status'] === 'ready') {
        fwrite(STDOUT, json_encode($result['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        exit(0);
    }

    $attempts[] = [
        'bindHost' => $bindHost,
        'host' => $requestHost,
        'port' => $port,
        'status' => $result['status'],
        'reason' => $result['payload']['reason'] ?? 'unknown',
        'stderr' => $result['payload']['stderr'] ?? '',
    ];
}

$allBlocked = $attempts !== [] && count(array_filter($attempts, static fn (array $attempt): bool => $attempt['status'] !== 'blocked')) === 0;
$status = $allBlocked ? 'blocked' : 'failed';
$reason = $allBlocked ? 'loopback_server_bind_blocked_on_host' : 'local_server_not_ready';

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'check' => 'dry_run_server',
    'status' => $status,
    'reason' => $reason,
    'attempts' => $attempts,
    'host' => AddressRuntimeBootstrap::hostReadiness(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
exit($allBlocked ? 2 : 1);
