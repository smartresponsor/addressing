<?php

declare(strict_types=1);

namespace App\Addressing\Tests\Unit\Quality;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class OpenApiPayloadContractTest extends TestCase
{
    public function testCreateContractMatchesRuntimePayloadBoundary(): void
    {
        $document = $this->openApiDocument();
        $schema = $document['components']['schemas']['AddressCreateRequest'] ?? null;

        self::assertIsArray($schema);
        self::assertSame(['line1', 'city', 'countryCode'], $schema['required'] ?? null);
        self::assertIsArray($schema['properties'] ?? null);
        self::assertArrayHasKey('countryCode', $schema['properties']);
        self::assertArrayNotHasKey('country', $schema['properties']);

        $responseSchema = $document['paths']['/api/address']['post']['responses']['201']['content']['application/json']['schema']['$ref'] ?? null;
        self::assertSame('#/components/schemas/AddressCreatedResponse', $responseSchema);
        self::assertSame(['id'], $document['components']['schemas']['AddressCreatedResponse']['required'] ?? null);
    }

    public function testReadContractUsesCurrentProjectionFieldNames(): void
    {
        $document = $this->openApiDocument();
        $properties = $document['components']['schemas']['AddressResponse']['properties'] ?? null;

        self::assertIsArray($properties);
        self::assertArrayHasKey('countryCode', $properties);
        self::assertArrayHasKey('validationStatus', $properties);
        self::assertArrayHasKey('validationProvider', $properties);
        self::assertArrayNotHasKey('country', $properties);
        self::assertArrayNotHasKey('status', $properties);
        self::assertArrayNotHasKey('provider', $properties);
    }

    /** @return array<string, mixed> */
    private function openApiDocument(): array
    {
        $document = Yaml::parseFile(dirname(__DIR__, 3).'/config/openapi/address_openapi.yaml');

        self::assertIsArray($document);

        return $document;
    }
}
