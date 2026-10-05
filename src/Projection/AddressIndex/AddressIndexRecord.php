<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Projection\AddressIndex;

use App\Addressing\Value\AddressCountryCode;
use App\Addressing\Value\AddressPostalCode;
use App\Addressing\Value\AddressStreetLine;
use App\Addressing\Value\Geocode\AddressGeocodeResult;
use App\Addressing\Value\Primitive\AddressRegion;

/**
 * Immutable projection payload persisted by the address index repository after normalization and optional geocoding.
 */
final readonly class AddressIndexRecord
{
    public function __construct(
        public string $digest,
        public string $line1,
        public ?string $line2,
        public string $city,
        public string $region,
        public string $postal,
        public string $country,
        public ?float $lat,
        public ?float $lon,
        public ?string $display,
        public ?string $provider,
        public ?float $confidence,
        public string $geoKey,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    /**
     * Builds the stable coordinate key used by the index for nullable latitude and longitude pairs.
     */
    public static function geokey(?float $lat, ?float $lon): string
    {
        if (null === $lat || null === $lon) {
            return '';
        }

        return sprintf('%+.5f:%+.5f', $lat, $lon);
    }

    /**
     * Creates an index record from canonical normalized address values and optional provider geocode evidence.
     *
     * @param array{line1: AddressStreetLine, line2: ?AddressStreetLine, city: string, region: AddressRegion, postal: AddressPostalCode, country: AddressCountryCode, digest: string} $norm
     */
    public static function fromNormalized(array $norm, ?AddressGeocodeResult $geocodeResult = null): self
    {
        $lat = $geocodeResult?->lat;
        $lon = $geocodeResult?->lon;
        $createdAt = new \DateTimeImmutable('now');
        $now = $createdAt->format('Y-m-d H:i:s');

        return new self(
            $norm['digest'],
            (string) $norm['line1'],
            null !== $norm['line2'] ? (string) $norm['line2'] : null,
            (string) $norm['city'],
            (string) $norm['region'],
            (string) $norm['postal'],
            $norm['country']->value(),
            $lat,
            $lon,
            $geocodeResult?->displayName,
            $geocodeResult?->provider,
            $geocodeResult?->confidence,
            self::geokey($lat, $lon),
            $now,
            $now,
        );
    }

    /**
     * Exposes the projection as the scalar persistence payload expected by the address index repository.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'digest' => $this->digest,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'region' => $this->region,
            'postal' => $this->postal,
            'country' => $this->country,
            'lat' => $this->lat,
            'lon' => $this->lon,
            'display' => $this->display,
            'provider' => $this->provider,
            'confidence' => $this->confidence,
            'geo_key' => $this->geoKey,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
