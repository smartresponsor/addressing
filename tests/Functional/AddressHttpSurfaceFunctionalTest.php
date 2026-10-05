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
    /** @var list<string> */
    private static array $functionalCoverage = [];

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

    public function testManageServiceHelperPathsNormalizeScopeAndEmptyPreview(): void
    {
        $services = $this->bootServices(__FUNCTION__);
        $manage = $services['manage'];

        $timestampMethod = new \ReflectionMethod(AddressManageHttpService::class, 'currentTimestampLiteral');
        $timestamp = $timestampMethod->invoke($manage);
        self::assertIsString($timestamp);
        self::assertNotFalse(\DateTimeImmutable::createFromFormat('Y-m-d H:i:sP', $timestamp));

        $nullableMethod = new \ReflectionMethod(AddressManageHttpService::class, 'nullableFormString');
        self::assertNull($nullableMethod->invoke($manage, [], 'ownerId'));
        self::assertNull($nullableMethod->invoke($manage, ['ownerId' => null], 'ownerId'));
        self::assertNull($nullableMethod->invoke($manage, ['ownerId' => '   '], 'ownerId'));
        self::assertSame('owner-1', $nullableMethod->invoke($manage, ['ownerId' => ' owner-1 '], 'ownerId'));

        try {
            $nullableMethod->invoke($manage, ['ownerId' => ['invalid']], 'ownerId');
            self::fail('Expected non-scalar ownerId to throw.');
        } catch (\RuntimeException $exception) {
            self::assertSame('invalid_ownerId', $exception->getMessage());
        }

        $dto = new \App\Addressing\DTO\AddressManageDTO();
        $dto->ownerId = null;
        $dto->vendorId = null;
        $previewMethod = new \ReflectionMethod(AddressManageHttpService::class, 'previewRows');
        self::assertSame([], $previewMethod->invoke($manage, $dto));
    }

    public function testOperationalAndValidatedApiRoutesThroughKernel(): void
    {
        $services = $this->bootServices(__FUNCTION__);
        $createContent = json_encode([
            'ownerId' => 'owner-1',
            'line1' => '500 Test Ave',
            'city' => 'Houston',
            'countryCode' => 'US',
        ], JSON_THROW_ON_ERROR);

        $createResponse = $services['kernel']->handle(Request::create(
            '/api/address',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $createContent,
        ));
        self::assertSame(201, $createResponse->getStatusCode());
        $created = json_decode((string) $createResponse->getContent(), true);
        self::assertIsArray($created);
        self::assertIsString($created['id'] ?? null);
        $id = $created['id'];

        $patchContent = json_encode([
            'revalidationPolicy' => 'monthly',
            'lastValidationStatus' => 'uncertain',
            'lastValidationScore' => 72,
        ], JSON_THROW_ON_ERROR);
        $patchResponse = $services['kernel']->handle(Request::create(
            '/api/address/'.$id.'?ownerId=owner-1',
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $patchContent,
        ));
        self::assertSame(200, $patchResponse->getStatusCode());
        $patched = json_decode((string) $patchResponse->getContent(), true);
        self::assertIsArray($patched);
        self::assertSame('monthly', $patched['revalidationPolicy'] ?? null);
        self::assertSame('uncertain', $patched['lastValidationStatus'] ?? null);
        self::assertSame(72, $patched['lastValidationScore'] ?? null);

        $batchContent = json_encode([
            'ids' => [$id, 'missing-id'],
            'revalidationPolicy' => 'quarterly',
        ], JSON_THROW_ON_ERROR);
        $batchResponse = $services['kernel']->handle(Request::create(
            '/api/address/operational-batch?ownerId=owner-1',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $batchContent,
        ));
        self::assertSame(200, $batchResponse->getStatusCode());
        $batch = json_decode((string) $batchResponse->getContent(), true);
        self::assertIsArray($batch);
        self::assertSame(2, $batch['requestedCount'] ?? null);
        self::assertSame(1, $batch['patchedCount'] ?? null);
        self::assertSame([$id], $batch['patchedIds'] ?? null);

        $validatedContent = json_encode([
            'line1Norm' => '500 test ave',
            'cityNorm' => 'houston',
            'validationProvider' => 'functional-test',
            'sourceSystem' => 'functional-suite',
            'sourceType' => 'validator',
            'normalizationVersion' => 'v1',
            'providerDigest' => 'functional-digest',
            'lastValidationStatus' => 'validated',
            'lastValidationScore' => 98,
        ], JSON_THROW_ON_ERROR);
        $validatedResponse = $services['kernel']->handle(Request::create(
            '/api/address/'.$id.'/validated?ownerId=owner-1',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $validatedContent,
        ));
        self::assertSame(200, $validatedResponse->getStatusCode());
        $validated = json_decode((string) $validatedResponse->getContent(), true);
        self::assertIsArray($validated);
        self::assertSame('validated', $validated['validationStatus'] ?? null);
        self::assertSame('functional-test', $validated['validationProvider'] ?? null);
        self::assertSame('functional-digest', $validated['providerDigest'] ?? null);

        $this->recordFunctionalCoverage([
            'PATCH /api/address/{id}',
            'POST /api/address/operational-batch',
            'POST /api/address/{id}/validated',
        ]);
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

        self::$functionalCoverage = array_values(array_unique([
            ...self::$functionalCoverage,
            ...$covered,
        ]));

        $encoded = json_encode([
            'schema' => 'address-http-functional-v1',
            'passedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'covered' => self::$functionalCoverage,
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
