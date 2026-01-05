<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
namespace App\Projection\AddressIndex;

use App\Integration\Geocode\GeocodeResult;

final class IndexRecord
{
    public function __construct(
        public readonly string $digest,
        public readonly string $line1,
        public readonly ?string $line2,
        public readonly string $city,
        public readonly string $region,
        public readonly string $postal,
        public readonly string $country,
        public readonly ?float $lat,
        public readonly ?float $lon,
        public readonly ?string $display,
        public readonly ?string $provider,
        public readonly ?float $confidence,
        public readonly string $geoKey,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) { }

    public static function geokey(?float $lat, ?float $lon): string
    {
        if ($lat === null || $lon === null) return '';
        return sprintf('%+.5f:%+.5f', $lat, $lon);
    }

    /** @param array{line1:object,line2:?object,city:string,region:object,postal:object,country:object,digest:string} $norm */
    public static function fromNormalized(array $norm, ?GeocodeResult $geo = null): self
    {
        $lat = $geo?->lat;
        $lon = $geo?->lon;
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        return new self(
            $norm['digest'],
            (string)$norm['line1'],
            $norm['line2'] !== null ? (string)$norm['line2'] : null,
            (string)$norm['city'],
            (string)$norm['region'],
            (string)$norm['postal'],
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
