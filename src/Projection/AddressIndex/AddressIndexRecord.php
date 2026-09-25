<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Projection\AddressIndex;

use App\Addressing\Integration\Geocode\AddressGeocodeResult;
use App\Addressing\Value\AddressCountryCode;
use App\Addressing\Value\AddressPostalCode;
use App\Addressing\Value\AddressStreetLine;
use App\Addressing\Value\Primitive\AddressRegion;

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

    public static function geokey(?float $lat, ?float $lon): string
    {
        if (null === $lat || null === $lon) {
            return '';
        }

        return sprintf('%+.5f:%+.5f', $lat, $lon);
    }

    /**
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
