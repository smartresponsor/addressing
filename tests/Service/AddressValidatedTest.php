<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Service;

use App\Contract\Message\AddressValidated;
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
}
