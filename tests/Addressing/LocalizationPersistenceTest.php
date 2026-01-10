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
final class LocalizationPersistenceTest extends AddressRepositoryTestCase
{
    /**
     * @return void
     */
    public function testLocaleAwareFieldsPersistRoundTrip(): void
    {
        $address = $this->makeAddress([
            'id' => 'addr-locale',
            'line1' => 'Ångströmvägen 1',
            'line2' => 'Апт. 12',
            'city' => 'München',
            'region' => 'Québec',
            'postalCode' => 'H2Y 1C6',
            'countryCode' => 'CA',
        ]);

        $this->repo->create($address);

        $fetched = $this->repo->get($address->id());
        static::assertNotNull($fetched);
        static::assertSame('Ångströmvägen 1', $fetched->line1());
        static::assertSame('Апт. 12', $fetched->line2());
        static::assertSame('München', $fetched->city());
        static::assertSame('Québec', $fetched->region());
        static::assertSame('H2Y 1C6', $fetched->postalCode());
        static::assertSame('CA', $fetched->countryCode());
    }
}
