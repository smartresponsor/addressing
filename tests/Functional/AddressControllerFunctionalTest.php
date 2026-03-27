<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Functional;

use App\Integration\Persistence\AddressSchemaManager;
use App\Kernel;
use App\Http\Controller\AddressController;
use PHPUnit\Framework\TestCase;
use PDO;
use Symfony\Component\HttpFoundation\Request;

final class AddressControllerFunctionalTest extends TestCase
{
    public function testCreateAndGetAddressFlow(): void
    {
        $controller = $this->bootController($this->freshSqlitePath(__FUNCTION__));

        $request = new Request([], [], [], [], [], [], json_encode([
            'ownerId' => 'owner-1',
            'vendorId' => 'vendor-1',
            'line1' => 'Main street 10',
            'city' => 'Austin',
            'countryCode' => 'us',
        ], JSON_UNESCAPED_UNICODE));

        $createResponse = $controller->create($request);
        self::assertSame(201, $createResponse->getStatusCode());

        $createPayload = json_decode((string) $createResponse->getContent(), true);
        self::assertIsArray($createPayload);
        self::assertArrayHasKey('id', $createPayload);

        $getRequest = new Request(['ownerId' => 'owner-1', 'vendorId' => 'vendor-1']);
        $getResponse = $controller->get($getRequest, (string) $createPayload['id']);

        self::assertSame(200, $getResponse->getStatusCode());

        $body = json_decode((string) $getResponse->getContent(), true);
        self::assertIsArray($body);
        self::assertSame('Main street 10', $body['line1']);
        self::assertSame('US', $body['countryCode']);
    }


    public function testManageFormRendersBootstrapLayout(): void
    {
        $controller = $this->bootController($this->freshSqlitePath(__FUNCTION__));
        $response = $controller->manage(new Request());

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Address manager', (string) $response->getContent());
        self::assertStringContainsString('btn', (string) $response->getContent());
    }

    private function bootController(string $sqlitePath): AddressController
    {
        $pdo = new PDO('sqlite:'.$sqlitePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        AddressSchemaManager::resetSchema($pdo, dirname(__DIR__, 2));

        putenv('ADDRESS_DB_DSN=sqlite:'.$sqlitePath);
        $_ENV['ADDRESS_DB_DSN'] = 'sqlite:'.$sqlitePath;
        $_SERVER['ADDRESS_DB_DSN'] = 'sqlite:'.$sqlitePath;
        $_SERVER['APP_ENV'] = 'test';
        $_SERVER['APP_DEBUG'] = '0';

        $kernel = new Kernel('test', false);
        $kernel->boot();

        /** @var AddressController $controller */
        $controller = $kernel->getContainer()->get(AddressController::class);

        return $controller;
    }

    private function freshSqlitePath(string $suffix): string
    {
        $path = dirname(__DIR__, 2).'/var/'.preg_replace('/[^A-Za-z0-9_-]/', '-', $suffix).'.sqlite';
        if (is_file($path)) {
            unlink($path);
        }

        return $path;
    }
}
