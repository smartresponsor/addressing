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
