<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Service;

use App\Contract\Message\Address\AddressValidated;
use PHPUnit\Framework\TestCase;

final class AddressValidatedTest extends TestCase
{
    public function testFingerprintStable(): void
    {
        $first = AddressValidated::fromArray([
            'line1Norm' => 'a',
            'cityNorm' => 'b',
            'validatedAt' => '2025-12-30T00:00:00Z',
        ]);
        $second = AddressValidated::fromArray([
            'line1Norm' => 'a',
            'cityNorm' => 'b',
            'validatedAt' => '2025-12-30T00:00:00Z',
        ]);

        self::assertSame($first->fingerprint(), $second->fingerprint());
    }

    public function testInvalidPerimeterValuesAreSanitized(): void
    {
        $validated = AddressValidated::fromArray([
            'sourceType' => 'strange-source',
            'governanceStatus' => 'wild',
            'revalidationPolicy' => 'sometimes',
            'lastValidationStatus' => 'mystery',
        ]);

        self::assertNull($validated->sourceType);
        self::assertSame('canonical', $validated->governanceStatus);
        self::assertNull($validated->revalidationPolicy);
        self::assertNull($validated->lastValidationStatus);
    }
}
