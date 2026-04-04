<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\E2E;

use Symfony\Component\Panther\Client;
use PHPUnit\Framework\TestCase;

final class AddressApiPantherE2ETest extends TestCase
{
    public function testManageFlowWorksInChrome(): void
    {
        if (!class_exists(Client::class)) {
            self::markTestSkipped('Panther is not installed.');
        }

        $chromeDriverBinary = self::chromeDriverBinary();
        if (null === $chromeDriverBinary) {
            self::markTestSkipped('chromedriver binary is not available in the host environment.');
        }

        $baseUri = getenv('PANTHER_EXTERNAL_BASE_URI') ?: null;
        $server = null;
        if (!is_string($baseUri) || $baseUri === '' || !self::isUrlReady($baseUri.'/address/manage')) {
            $server = self::startLocalPhpServer();
            $baseUri = $server['base_uri'];
        }

        $chromeBinary = getenv('PANTHER_CHROME_BINARY');
        if (is_string($chromeBinary) && $chromeBinary !== '') {
            $_SERVER['PANTHER_CHROME_BINARY'] = $chromeBinary;
        }
        $_SERVER['PANTHER_NO_SANDBOX'] = '1';
        $suffix = bin2hex(random_bytes(4));
        $line1 = '100 Panther Way '.$suffix;
        $ownerId = 'panther-owner-'.$suffix;
        $vendorId = 'panther-vendor-'.$suffix;

        $client = null;
        try {
            $client = Client::createChromeClient($chromeDriverBinary, [
                '--headless=new',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--no-sandbox',
                '--window-size=1200,1100',
            ], [
                'port' => self::findFreePort(),
            ], $baseUri);

            $crawler = $client->request('GET', '/address/manage');
            self::assertStringContainsString('Address manager', $client->getPageSource());

            $form = $crawler->selectButton('Create address')->form([
                'address_manage[line1]' => $line1,
                'address_manage[city]' => 'Austin',
                'address_manage[countryCode]' => 'US',
                'address_manage[ownerId]' => $ownerId,
                'address_manage[vendorId]' => $vendorId,
            ]);

            $client->submit($form);
            self::assertStringContainsString('Address created successfully:', $client->getPageSource());
            self::assertStringContainsString($line1, $client->getPageSource());
        } finally {
            if ($client instanceof Client) {
                $client->quit();
            }
            self::stopLocalPhpServer($server);
        }
    }

    private static function chromeDriverBinary(): ?string
    {
        $configured = getenv('PANTHER_CHROME_DRIVER_BINARY') ?: ($_SERVER['PANTHER_CHROME_DRIVER_BINARY'] ?? null);
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            $resolved = realpath($configured);

            return false === $resolved ? $configured : $resolved;
        }

        foreach ([
            './bin/chromedriver.exe',
            './bin/chromedriver',
            './drivers/chromedriver.exe',
            './drivers/chromedriver',
            './vendor/bin/chromedriver.exe',
            './vendor/bin/chromedriver',
        ] as $candidate) {
            if (is_file($candidate)) {
                $resolved = realpath($candidate);

                return false === $resolved ? $candidate : $resolved;
            }
        }

        $finder = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN' ? 'where chromedriver' : 'command -v chromedriver';
        $output = [];
        $exitCode = 0;
        @exec($finder.' 2>&1', $output, $exitCode);

        if (0 !== $exitCode || $output === []) {
            return null;
        }

        foreach ($output as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && is_file($candidate)) {
                $resolved = realpath($candidate);

                return false === $resolved ? $candidate : $resolved;
            }
        }

        return null;
    }

    /**
     * @return array{base_uri: string, process: resource, log: string}|null
     */
    private static function startLocalPhpServer(): ?array
    {
        $projectRoot = dirname(__DIR__, 2);
        $port = self::findFreePort();
        $baseUri = 'http://127.0.0.1:'.$port;
        $logFile = tempnam(sys_get_temp_dir(), 'addressing-panther-server-');
        self::assertNotFalse($logFile);

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['file', $logFile, 'a'],
            2 => ['file', $logFile, 'a'],
        ];

        $phpBinary = self::phpBinary();
        $process = proc_open([$phpBinary, '-S', '127.0.0.1:'.$port, '-t', 'public', 'public/router.php'], $descriptorSpec, $pipes, $projectRoot);
        if (!is_resource($process)) {
            @unlink($logFile);
            self::fail('Failed to start local PHP server for Panther.');
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        if (!self::isUrlReady($baseUri.'/address/manage', 30)) {
            $log = (string) @file_get_contents($logFile);
            self::stopLocalPhpServer(['base_uri' => $baseUri, 'process' => $process, 'log' => $logFile]);
            self::fail("Timed out waiting for local PHP server.\n".$log);
        }

        return ['base_uri' => $baseUri, 'process' => $process, 'log' => $logFile];
    }

    /**
     * @param array{base_uri: string, process: resource, log: string}|null $server
     */
    private static function stopLocalPhpServer(?array $server): void
    {
        if (null === $server) {
            return;
        }

        proc_terminate($server['process']);
        proc_close($server['process']);
        @unlink($server['log']);
    }

    private static function isUrlReady(string $url, int $timeoutSeconds = 5): bool
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

    private static function phpBinary(): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => 'C:\\PHP\\php-8.4.13-nts-Win32-vs17-x64\\php.exe',
            default => is_file('/usr/bin/php8.4') ? '/usr/bin/php8.4' : PHP_BINARY,
        };
    }

    private static function findFreePort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if (false === $socket) {
            self::fail(sprintf('Failed to allocate a free local port: %s (%d)', $errorMessage, $errorCode));
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        if (!is_string($name) || !str_contains($name, ':')) {
            self::fail('Failed to determine allocated local port.');
        }

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
