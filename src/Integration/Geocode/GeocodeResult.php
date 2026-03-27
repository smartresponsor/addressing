<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Integration\Geocode;

/**
 *
 */

/**
 *
 */
final readonly class GeocodeResult
{
    /**
     * @param float|null $lat
     * @param float|null $lon
     * @param string|null $displayName
     * @param string|null $provider
     * @param float|null $confidence
     */
    public function __construct(
        public ?float  $lat,
        public ?float  $lon,
        public ?string $displayName,
        public ?string $provider,
        public ?float  $confidence,
    )
    {
    }
}
