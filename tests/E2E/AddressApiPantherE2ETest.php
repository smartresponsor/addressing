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

        if (!self::hasChromeDriverBinary()) {
            self::markTestSkipped('chromedriver binary is not available in the host environment.');
        }

        $baseUri = getenv('PANTHER_EXTERNAL_BASE_URI') ?: 'http://127.0.0.1';
        $chromeBinary = getenv('PANTHER_CHROME_BINARY');
        if (is_string($chromeBinary) && $chromeBinary !== '') {
            $_SERVER['PANTHER_CHROME_BINARY'] = $chromeBinary;
        }
        $_SERVER['PANTHER_NO_SANDBOX'] = '1';
        $suffix = bin2hex(random_bytes(4));
        $line1 = '100 Panther Way '.$suffix;
        $ownerId = 'panther-owner-'.$suffix;
        $vendorId = 'panther-vendor-'.$suffix;

        $client = Client::createChromeClient(null, [
            '--headless=new',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--no-sandbox',
            '--window-size=1200,1100',
        ], [], $baseUri);

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
    }

    private static function hasChromeDriverBinary(): bool
    {
        $configured = getenv('PANTHER_CHROME_DRIVER_BINARY') ?: ($_SERVER['PANTHER_CHROME_DRIVER_BINARY'] ?? null);
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return true;
        }

        $commands = [
            'chromedriver --version',
            'where chromedriver',
            'command -v chromedriver',
        ];

        foreach ($commands as $command) {
            $output = [];
            $exitCode = 0;
            @exec($command.' 2>&1', $output, $exitCode);
            if (0 === $exitCode && $output !== []) {
                return true;
            }
        }

        return false;
    }
}
