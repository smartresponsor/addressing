<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Functional;

use App\Addressing\Kernel;
use App\Addressing\Service\Http\Address\AddressManageHttpService;
use App\Addressing\Service\Http\Address\AddressReadHttpService;
use App\Addressing\Service\Http\Address\AddressWriteHttpService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
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
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $services['requestStack']->push($request);

        try {
            $response = $services['manage']->manage($request);
        } finally {
            $services['requestStack']->pop();
        }

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Address manager', (string) $response->getContent());
        self::assertStringContainsString('btn', (string) $response->getContent());
    }

    public function testApiReadSurfaceRoutesThroughKernel(): void
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

        $createResponse = $services['kernel']->handle(Request::create(
            '/api/address',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $content,
        ));
        self::assertSame(201, $createResponse->getStatusCode());

        $createPayload = json_decode((string) $createResponse->getContent(), true);
        self::assertIsArray($createPayload);
        self::assertIsString($createPayload['id'] ?? null);
        $id = $createPayload['id'];

        $readUrls = [
            '/api/address/'.$id.'?ownerId=owner-1',
            '/api/address/page?ownerId=owner-1',
            '/api/address/search?ownerId=owner-1&q=Main',
            '/api/address/queue-summary?ownerId=owner-1',
            '/api/address/country-portfolio?ownerId=owner-1',
            '/api/address/source-portfolio?ownerId=owner-1',
            '/api/address/validation-portfolio?ownerId=owner-1',
            '/api/address/normalization-portfolio?ownerId=owner-1',
            '/api/address/'.$id.'/governance-cluster?ownerId=owner-1',
        ];

        foreach ($readUrls as $url) {
            $response = $services['kernel']->handle(Request::create($url, 'GET'));
            self::assertSame(200, $response->getStatusCode(), $url);
        }

        $deleteResponse = $services['kernel']->handle(Request::create(
            '/api/address/'.$id.'?ownerId=owner-1',
            'DELETE',
        ));
        self::assertSame(204, $deleteResponse->getStatusCode());

        $this->recordFunctionalCoverage([
            'POST /api/address',
            'GET /api/address/page',
            'GET /api/address/search',
            'GET /api/address/queue-summary',
            'GET /api/address/country-portfolio',
            'GET /api/address/source-portfolio',
            'GET /api/address/validation-portfolio',
            'GET /api/address/normalization-portfolio',
            'GET /api/address/{id}',
            'DELETE /api/address/{id}',
            'GET /api/address/{id}/governance-cluster',
        ]);
    }

    /** @param list<string> $covered */
    private function recordFunctionalCoverage(array $covered): void
    {
        $coverageDir = dirname(__DIR__, 2).'/var/coverage';
        if (!is_dir($coverageDir)) {
            self::assertTrue(mkdir($coverageDir, 0777, true) || is_dir($coverageDir));
        }

        $encoded = json_encode([
            'schema' => 'address-http-functional-v1',
            'passedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'covered' => $covered,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        self::assertNotFalse(file_put_contents($coverageDir.'/address-http-functional.json', $encoded.PHP_EOL));
    }

    /**
     * @return array{write: AddressWriteHttpService, read: AddressReadHttpService, manage: AddressManageHttpService, requestStack: RequestStack, kernel: Kernel}
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
        /** @var RequestStack $requestStack */
        $requestStack = $kernel->getContainer()->get('request_stack');

        return [
            'write' => $addressWriteHttpService,
            'read' => $addressReadHttpService,
            'manage' => $addressManageHttpService,
            'requestStack' => $requestStack,
            'kernel' => $kernel,
        ];
    }
}
