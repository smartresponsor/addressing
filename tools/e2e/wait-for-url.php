<?php
declare(strict_types=1);

function waitForUrl(string $url, int $timeoutSeconds = 30): bool
{
    $deadline = microtime(true) + max(1, $timeoutSeconds);

    while (microtime(true) < $deadline) {
        $context = stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'timeout' => 2,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        if (is_string($body) && str_contains($statusLine, '200')) {
            return true;
        }

        usleep(250000);
    }

    return false;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($argc < 2) {
        fwrite(STDERR, "Usage: php tools/e2e/wait-for-url.php <url> [timeout-seconds]\n");
        exit(1);
    }

    $ok = waitForUrl($argv[1], isset($argv[2]) ? (int) $argv[2] : 30);
    if (!$ok) {
        fwrite(STDERR, sprintf("Timed out waiting for %s.\n", $argv[1]));
        exit(1);
    }
}
