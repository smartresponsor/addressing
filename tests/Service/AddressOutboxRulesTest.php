<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */

declare(strict_types=1);

namespace Tests\Service;

use App\Entity\Address\AddressData;
use App\Entity\Address\AddressOutboxRules;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AddressOutboxRulesTest extends TestCase
{
    public function testAddressCreatedPayloadNormalizesCountryCode(): void
    {
        $address = new AddressData(
            '01HZXMTKZP9J3BRKZK9C8ZK2Q5',
            'owner-1',
            null,
            'Line 1',
            null,
            'City',
            'Region',
            '12345',
            'us',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'new',
            null,
            null,
            null,
            '2025-01-01T00:00:00+00:00',
            null,
            null
        );

        $payload = AddressOutboxRules::addressCreatedPayload($address);

        $this->assertSame('US', $payload['countryCode']);
        $this->assertSame('2025-01-01T00:00:00+00:00', $payload['createdAt']);
    }

    public function testAddressValidatedAppliedPayloadValidatesSha(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AddressOutboxRules::addressValidatedAppliedPayload(
            '01HZXMTKZP9J3BRKZK9C8ZK2Q5',
            'not-a-sha',
            'nominatim',
            new DateTimeImmutable('2025-02-02T10:00:00+00:00'),
            true,
            'house',
            80,
            null
        );
    }

    public function testAddressValidatedAppliedPayload(): void
    {
        $validatedAt = new DateTimeImmutable('2025-02-02T10:00:00+00:00');
        $fingerprint = str_repeat('a', 64);
        $rawSha = str_repeat('b', 64);

        $payload = AddressOutboxRules::addressValidatedAppliedPayload(
            '01HZXMTKZP9J3BRKZK9C8ZK2Q5',
            $fingerprint,
            'nominatim',
            $validatedAt,
            true,
            'house',
            80,
            $rawSha
        );

        $this->assertSame($fingerprint, $payload['fingerprint']);
        $this->assertSame('nominatim', $payload['provider']);
        $this->assertSame($validatedAt->format(DATE_ATOM), $payload['validatedAt']);
        $this->assertTrue($payload['deliverable']);
        $this->assertSame('house', $payload['granularity']);
        $this->assertSame(80, $payload['quality']);
        $this->assertSame($rawSha, $payload['rawSha256']);
    }
}
