<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */

declare(strict_types=1);

namespace Tests\Addressing;

/**
 *
 */

/**
 *
 */
final class NormalizationStabilityTest extends AddressRepositoryTestCase
{
    /**
     * @return void
     */
    public function testNormalizedFieldsRoundTripWithoutMutation(): void
    {
        $address = $this->makeAddress([
            'id' => 'addr-norm',
            'line1' => ' 123  MAIN  St ',
            'city' => ' New  York ',
            'region' => 'ny',
            'postalCode' => ' 10001 ',
            'line1Norm' => '123 MAIN ST',
            'cityNorm' => 'NEW YORK',
            'regionNorm' => 'NY',
            'postalCodeNorm' => '10001',
        ]);

        $this->repo->create($address);

        $fetched = $this->repo->get($address->id());
        static::assertNotNull($fetched);
        static::assertSame('123 MAIN ST', $fetched->line1Norm());
        static::assertSame('NEW YORK', $fetched->cityNorm());
        static::assertSame('NY', $fetched->regionNorm());
        static::assertSame('10001', $fetched->postalCodeNorm());
    }
}
