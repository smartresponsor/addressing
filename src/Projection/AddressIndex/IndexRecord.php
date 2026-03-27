<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Projection\AddressIndex;

use App\Integration\Geocode\GeocodeResult;
use App\Value\CountryCode;
use App\Value\PostalCode;
use App\Value\Primitive\Region;
use App\Value\StreetLine;

final readonly class IndexRecord
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
     * @param array{line1: StreetLine, line2: ?StreetLine, city: string, region: Region, postal: PostalCode, country: CountryCode, digest: string} $norm
     */
    public static function fromNormalized(array $norm, ?GeocodeResult $geo = null): self
    {
        $lat = $geo?->lat;
        $lon = $geo?->lon;
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

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
            $geo?->displayName,
            $geo?->provider,
            $geo?->confidence,
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
