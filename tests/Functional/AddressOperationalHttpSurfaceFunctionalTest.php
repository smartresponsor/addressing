<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Addressing\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Tests\Support\TestDatabase;
use Tests\Support\TestRuntimeEnvironment;

final class AddressOperationalHttpSurfaceFunctionalTest extends TestCase
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

    public function testOperationalMutationSurfaceRoutesThroughKernel(): void
    {
        $kernel = $this->bootKernel(__FUNCTION__);
        $id = $this->createAddress($kernel);

        $patchResponse = $kernel->handle(Request::create(
            '/api/address/'.$id.'?ownerId=owner-1',
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $this->json([
                'lastValidationStatus' => 'uncertain',
                'lastValidationScore' => 55,
            ]),
        ));
        self::assertSame(200, $patchResponse->getStatusCode());

        $batchResponse = $kernel->handle(Request::create(
            '/api/address/operational-batch?ownerId=owner-1',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $this->json([
                'ids' => [$id],
                'lastValidationStatus' => 'validated',
                'lastValidationScore' => 90,
            ]),
        ));
        self::assertSame(200, $batchResponse->getStatusCode());

        $batchPayload = json_decode((string) $batchResponse->getContent(), true);
        self::assertIsArray($batchPayload);
        self::assertSame(1, $batchPayload['requestedCount'] ?? null);
        self::assertSame(1, $batchPayload['patchedCount'] ?? null);
        self::assertSame([$id], $batchPayload['patchedIds'] ?? null);
        self::assertSame([], $batchPayload['failed'] ?? null);

        $validatedResponse = $kernel->handle(Request::create(
            '/api/address/'.$id.'/validated?ownerId=owner-1',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $this->json([
                'provider' => 'functional-test',
                'line1Norm' => '10 Main Street',
                'cityNorm' => 'Austin',
                'lastValidationStatus' => 'validated',
                'lastValidationScore' => 100,
            ]),
        ));
        self::assertSame(200, $validatedResponse->getStatusCode());

        $validatedPayload = json_decode((string) $validatedResponse->getContent(), true);
        self::assertIsArray($validatedPayload);
        self::assertSame($id, $validatedPayload['id'] ?? null);
        self::assertSame('10 Main Street', $validatedPayload['line1Norm'] ?? null);
        self::assertSame('Austin', $validatedPayload['cityNorm'] ?? null);
    }

    public function testOperationalMutationSurfaceCoversInvalidMissingAndBatchFailurePaths(): void
    {
        $kernel = $this->bootKernel(__FUNCTION__);
        $id = $this->createAddress($kernel);
        $missingId = (string) new \Symfony\Component\Uid\Ulid();

        $invalidPatch = $kernel->handle(Request::create(
            '/api/address/'.$id.'?ownerId=owner-1',
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: 'not-json',
        ));
        self::assertSame(422, $invalidPatch->getStatusCode());

        $missingPatch = $kernel->handle(Request::create(
            '/api/address/'.$missingId.'?ownerId=owner-1',
            'PATCH',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $this->json(['lastValidationStatus' => 'validated']),
        ));
        self::assertSame(404, $missingPatch->getStatusCode());

        $invalidBatch = $kernel->handle(Request::create(
            '/api/address/operational-batch?ownerId=owner-1',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $this->json(['lastValidationStatus' => 'validated']),
        ));
        self::assertSame(422, $invalidBatch->getStatusCode());

        $partialBatch = $kernel->handle(Request::create(
            '/api/address/operational-batch?ownerId=owner-1',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $this->json([
                'ids' => [$id, 'missing'],
                'lastValidationStatus' => 'validated',
            ]),
        ));
        self::assertSame(200, $partialBatch->getStatusCode());
        $partialPayload = json_decode((string) $partialBatch->getContent(), true);
        self::assertIsArray($partialPayload);
        self::assertSame(2, $partialPayload['requestedCount'] ?? null);
        self::assertSame(1, $partialPayload['patchedCount'] ?? null);

        $failedBatch = $kernel->handle(Request::create(
            '/api/address/operational-batch?ownerId=owner-1',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $this->json([
                'ids' => [$id],
                'governanceStatus' => 'duplicate',
            ]),
        ));
        self::assertSame(200, $failedBatch->getStatusCode());
        $failedPayload = json_decode((string) $failedBatch->getContent(), true);
        self::assertIsArray($failedPayload);
        self::assertCount(1, $failedPayload['failed'] ?? []);

        $invalidValidated = $kernel->handle(Request::create(
            '/api/address/'.$id.'/validated?ownerId=owner-1',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: 'not-json',
        ));
        self::assertSame(422, $invalidValidated->getStatusCode());

        $missingValidated = $kernel->handle(Request::create(
            '/api/address/'.$missingId.'/validated?ownerId=owner-1',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $this->json(['provider' => 'functional-test']),
        ));
        self::assertSame(422, $missingValidated->getStatusCode());

        $emptyPage = $kernel->handle(Request::create('/api/address/page?ownerId=owner-empty', 'GET'));
        self::assertSame(200, $emptyPage->getStatusCode());
        $emptyPagePayload = json_decode((string) $emptyPage->getContent(), true);
        self::assertIsArray($emptyPagePayload);
        self::assertSame([], $emptyPagePayload['items'] ?? null);
        self::assertNull($emptyPagePayload['nextCursor'] ?? null);

        $missingGovernance = $kernel->handle(Request::create(
            '/api/address/'.$missingId.'/governance-cluster?ownerId=owner-1',
            'GET',
        ));
        self::assertSame(404, $missingGovernance->getStatusCode());
    }

    private function bootKernel(string $suffix): Kernel
    {
        $this->sqlitePath = TestDatabase::freshSqlitePath($suffix);
        $connection = TestDatabase::createSqliteConnection($this->sqlitePath);
        TestDatabase::resetAddressSchema($connection);
        $connection->close();

        TestRuntimeEnvironment::configureSqliteAddressRuntime($this->sqlitePath);

        $kernel = new Kernel('test', false);
        $kernel->boot();

        return $kernel;
    }

    private function createAddress(Kernel $kernel): string
    {
        $response = $kernel->handle(Request::create(
            '/api/address',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $this->json([
                'ownerId' => 'owner-1',
                'line1' => 'Main street 10',
                'city' => 'Austin',
                'countryCode' => 'us',
            ]),
        ));
        self::assertSame(201, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        self::assertIsArray($payload);
        self::assertIsString($payload['id'] ?? null);

        return $payload['id'];
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
