<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Functional;

use App\Kernel;
use App\Service\Http\Address\AddressManageHttpService;
use App\Service\Http\Address\AddressReadHttpService;
use App\Service\Http\Address\AddressWriteHttpService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Tests\Support\TestDatabase;
use Tests\Support\TestRuntimeEnvironment;

final class AddressHttpSurfaceFunctionalTest extends TestCase
{
    private ?string $sqlitePath = null;

    protected function tearDown(): void
    {
        TestRuntimeEnvironment::clearSqliteAddressRuntime();
        if (is_string($this->sqlitePath) && is_file($this->sqlitePath)) {
            unlink($this->sqlitePath);
        }
        $this->sqlitePath = null;
    }

    public function testCreateAndGetAddressFlow(): void
    {
        $services = $this->bootServices(__FUNCTION__);

        $content = json_encode([
            'ownerId' => 'owner-1',
            'vendorId' => 'vendor-1',
            'line1' => 'Main street 10',
            'city' => 'Austin',
            'countryCode' => 'us',
        ], JSON_UNESCAPED_UNICODE);
        self::assertIsString($content);

        $request = new Request([], [], [], [], [], [], $content);

        $createResponse = $services['write']->create($request);
        self::assertSame(201, $createResponse->getStatusCode());

        $createPayload = json_decode((string) $createResponse->getContent(), true);
        self::assertIsArray($createPayload);
        self::assertArrayHasKey('id', $createPayload);

        $getRequest = new Request(['ownerId' => 'owner-1']);
        $getResponse = $services['read']->get($getRequest, (string) $createPayload['id']);

        self::assertSame(200, $getResponse->getStatusCode());

        $body = json_decode((string) $getResponse->getContent(), true);
        self::assertIsArray($body);
        self::assertSame('Main street 10', $body['line1']);
        self::assertSame('US', $body['countryCode']);
    }

    public function testManageFormRendersBootstrapLayout(): void
    {
        $services = $this->bootServices(__FUNCTION__);
        $response = $services['manage']->manage(new Request());

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Address manager', (string) $response->getContent());
        self::assertStringContainsString('btn', (string) $response->getContent());
    }

    /**
     * @return array{write: AddressWriteHttpService, read: AddressReadHttpService, manage: AddressManageHttpService}
     */
    private function bootServices(string $suffix): array
    {
        $this->sqlitePath = TestDatabase::freshSqlitePath($suffix);
        $connection = TestDatabase::createSqliteConnection($this->sqlitePath);
        TestDatabase::resetAddressSchema($connection);
        $connection->close();

        TestRuntimeEnvironment::configureSqliteAddressRuntime($this->sqlitePath);

        $kernel = new Kernel('test', false);
        $kernel->boot();

        /** @var AddressWriteHttpService $addressWriteHttpService */
        $addressWriteHttpService = $kernel->getContainer()->get(AddressWriteHttpService::class);
        /** @var AddressReadHttpService $addressReadHttpService */
        $addressReadHttpService = $kernel->getContainer()->get(AddressReadHttpService::class);
        /** @var AddressManageHttpService $addressManageHttpService */
        $addressManageHttpService = $kernel->getContainer()->get(AddressManageHttpService::class);

        return [
            'write' => $addressWriteHttpService,
            'read' => $addressReadHttpService,
            'manage' => $addressManageHttpService,
        ];
    }
}
