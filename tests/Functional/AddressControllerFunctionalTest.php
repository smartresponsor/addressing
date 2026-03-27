<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Functional;

use App\Http\Controller\AddressController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Tests\Support\TestDatabase;

final class AddressControllerFunctionalTest extends TestCase
{
    public function testCreateAndGetAddressFlow(): void
    {
        $pdo = TestDatabase::createPdo();
        TestDatabase::resetAddressSchema($pdo);

        $controller = AddressController::fromPg($pdo);

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
        $pdo = TestDatabase::createPdo();
        TestDatabase::resetAddressSchema($pdo);

        $controller = AddressController::fromPg($pdo);
        $response = $controller->manage(new Request());

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Address manager', (string) $response->getContent());
        self::assertStringContainsString('btn', (string) $response->getContent());
    }
}
