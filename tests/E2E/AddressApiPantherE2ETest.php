<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\E2E;

use PHPUnit\Framework\TestCase;

final class AddressApiPantherE2ETest extends TestCase
{
    public function testPantherLibraryIsAvailableForBrowserE2E(): void
    {
        if (!class_exists('Symfony\\Component\\Panther\\Client')) {
            self::markTestSkipped('Panther is not installed in the current environment.');
        }

        $clientClass = 'Symfony\\Component\\Panther\\Client';
        $this->assertTrue(method_exists($clientClass, 'createChromeClient'));
    }
}
