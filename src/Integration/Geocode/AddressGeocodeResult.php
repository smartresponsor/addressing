<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Integration\Geocode;

final readonly class AddressGeocodeResult
{
    public function __construct(
        public ?float $lat,
        public ?float $lon,
        public ?string $displayName,
        public ?string $provider,
        public ?float $confidence,
    ) {
    }
}
