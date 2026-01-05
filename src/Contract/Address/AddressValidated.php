<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

declare(strict_types=1);

namespace App\Contract\Address;

use DateTimeImmutable;
use JsonSerializable;

final class AddressValidated implements JsonSerializable
{
    public function __construct(
        public readonly ?string $line1Norm,
        public readonly ?string $cityNorm,
        public readonly ?string $regionNorm,
        public readonly ?string $postalCodeNorm,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $geohash,
        public readonly ?string $validationProvider,
        public readonly ?DateTimeImmutable $validatedAt,
        public readonly ?string $dedupeKey,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $line1Norm = self::asNullableString($data['line1Norm'] ?? null);
        $cityNorm = self::asNullableString($data['cityNorm'] ?? null);
        $regionNorm = self::asNullableString($data['regionNorm'] ?? null);
        $postalCodeNorm = self::asNullableString($data['postalCodeNorm'] ?? null);
        $latitude = self::asNullableFloat($data['latitude'] ?? null);
        $longitude = self::asNullableFloat($data['longitude'] ?? null);
        $geohash = self::asNullableString($data['geohash'] ?? null);
        $validationProvider = self::asNullableString($data['validationProvider'] ?? null);
        $validatedAt = self::asNullableDate($data['validatedAt'] ?? null);
        $dedupeKey = self::asNullableString($data['dedupeKey'] ?? null);

        return new self(
            $line1Norm,
            $cityNorm,
            $regionNorm,
            $postalCodeNorm,
            $latitude,
            $longitude,
            $geohash,
            $validationProvider,
            $validatedAt,
            $dedupeKey,
        );
    }

    public function fingerprint(): string
    {
        $arr = $this->jsonSerialize();
        return hash('sha256', json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, mixed> */
    public function toDbArray(): array
    {
        return [
            'line1_norm' => $this->line1Norm,
            'city_norm' => $this->cityNorm,
            'region_norm' => $this->regionNorm,
            'postal_code_norm' => $this->postalCodeNorm,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'geohash' => $this->geohash,
            'validation_provider' => $this->validationProvider,
            'validated_at' => $this->validatedAt?->format(DATE_ATOM),
            'dedupe_key' => $this->dedupeKey,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'line1Norm' => $this->line1Norm,
            'cityNorm' => $this->cityNorm,
            'regionNorm' => $this->regionNorm,
            'postalCodeNorm' => $this->postalCodeNorm,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'geohash' => $this->geohash,
            'validationProvider' => $this->validationProvider,
            'validatedAt' => $this->validatedAt?->format(DATE_ATOM),
            'dedupeKey' => $this->dedupeKey,
        ];
    }

    private static function asNullableString(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string)$v);
        return $s === '' ? null : $s;
    }

    private static function asNullableFloat(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_float($v) || is_int($v)) {
            return (float)$v;
        }
        if (is_string($v) && is_numeric($v)) {
            return (float)$v;
        }
        return null;
    }

    private static function asNullableDate(mixed $v): ?DateTimeImmutable
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            return new DateTimeImmutable((string)$v);
        } catch (\Throwable) {
            return null;
        }
    }
}
