<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

declare(strict_types=1);

namespace App\Contract\Message\Address;

final readonly class AddressValidated implements \JsonSerializable
{
    public function __construct(
        public ?string $line1Norm,
        public ?string $cityNorm,
        public ?string $regionNorm,
        public ?string $postalCodeNorm,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $geohash,
        public ?string $validationProvider,
        public ?\DateTimeImmutable $validatedAt,
        public ?string $dedupeKey,
        /** @var array<string, mixed>|null */
        public ?array $raw = null,
        public ?AddressValidationVerdict $verdict = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
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

        $raw = null;
        if (array_key_exists('raw', $data) && is_array($data['raw'])) {
            /** @var array<string, mixed> $raw */
            $raw = $data['raw'];
        }

        $verdictArr = null;
        if (array_key_exists('verdict', $data) && is_array($data['verdict'])) {
            /** @var array<string, mixed> $verdictArr */
            $verdictArr = $data['verdict'];
        } elseif (array_key_exists('validationVerdict', $data) && is_array($data['validationVerdict'])) {
            /** @var array<string, mixed> $verdictArr */
            $verdictArr = $data['validationVerdict'];
        }

        $verdict = AddressValidationVerdict::fromArray($verdictArr);

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
            $raw,
            $verdict,
        );
    }

    public function fingerprint(): string
    {
        $arr = $this->jsonSerialize();
        $json = json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            $json = '';
        }

        return hash('sha256', $json);
    }

    /** @return array<string, mixed> */
    public function toDbArray(): array
    {
        $verdictArr = $this->verdict?->jsonSerialize();

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
            'validation_raw' => $this->encodeJsonNullable($this->raw),
            'validation_verdict' => $this->encodeJsonNullable($verdictArr),
            'validation_deliverable' => $this->verdict?->deliverable,
            'validation_granularity' => $this->verdict?->granularity,
            'validation_quality' => $this->verdict?->quality,
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
            'raw' => $this->raw,
            'verdict' => $this->verdict?->jsonSerialize(),
        ];
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function encodeJsonNullable(?array $data): ?string
    {
        if (null === $data) {
            return null;
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            return null;
        }

        return $json;
    }

    private static function asNullableString(mixed $v): ?string
    {
        if (null === $v) {
            return null;
        }
        if (is_string($v)) {
            $s = trim($v);

            return '' === $s ? null : $s;
        }
        if (is_int($v) || is_float($v) || is_bool($v) || $v instanceof \Stringable) {
            $s = trim((string) $v);

            return '' === $s ? null : $s;
        }

        return null;
    }

    private static function asNullableFloat(mixed $v): ?float
    {
        if (null === $v || '' === $v) {
            return null;
        }
        if (is_float($v) || is_int($v)) {
            return (float) $v;
        }
        if (is_string($v) && is_numeric($v)) {
            return (float) $v;
        }

        return null;
    }

    private static function asNullableDate(mixed $v): ?\DateTimeImmutable
    {
        if (null === $v || '' === $v) {
            return null;
        }
        try {
            if ($v instanceof \DateTimeInterface) {
                return \DateTimeImmutable::createFromInterface($v);
            }
            if (is_string($v)) {
                return new \DateTimeImmutable($v);
            }
            if (is_int($v)) {
                return new \DateTimeImmutable('@'.$v);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
