<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */

declare(strict_types=1);

namespace Tests\Service;

use App\Entity\Address\AddressOutboxRules;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 *
 */

/**
 *
 */
final class AddressOutboxRulesTest extends TestCase
{
    /**
     * @return void
     */
    public function testCountryCodeNormalizesToIso2Uppercase(): void
    {
        $payload = AddressOutboxRules::addressCreatedPayload(
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            null,
            null,
            'us',
            '2025-01-01 00:00:00+00:00'
        );

        static::assertSame('US', $payload['countryCode']);
    }

    /**
     * @return void
     */
    public function testSha256Validation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AddressOutboxRules::addressValidatedAppliedPayload(
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'invalid-sha',
            'locator',
            '2025-12-30T00:00:00+00:00',
            null,
            null,
            null,
            null
        );
    }

    /**
     * @return void
     */
    public function testValidAddressValidatedAppliedPayload(): void
    {
        $payload = AddressOutboxRules::addressValidatedAppliedPayload(
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'a3e1a80cf10e8f167a4a2ee5a90b5db2f7c4a6d24e1b4c5d83d5f87643c5a4fe',
            'locator',
            '2025-12-30T00:00:00+00:00',
            true,
            'address',
            90,
            '0f1a927f0c6801f3d9d33f2d9a3497f0b8e0cf0df4f3de4d5c6c413e5460a8d2'
        );

        static::assertSame(
            [
                'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                'fingerprint' => 'a3e1a80cf10e8f167a4a2ee5a90b5db2f7c4a6d24e1b4c5d83d5f87643c5a4fe',
                'provider' => 'locator',
                'validatedAt' => '2025-12-30T00:00:00+00:00',
                'deliverable' => true,
                'granularity' => 'address',
                'quality' => 90,
                'rawSha256' => '0f1a927f0c6801f3d9d33f2d9a3497f0b8e0cf0df4f3de4d5c6c413e5460a8d2',
            ],
            $payload
        );
    }
}
