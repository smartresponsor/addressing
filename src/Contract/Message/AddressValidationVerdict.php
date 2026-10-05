<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Contract\Message;

/**
 * Carries the normalized validation verdict retained by Addressing after provider evidence is interpreted.
 *
 * The value keeps deliverability, granularity, quality, and provider signals transport-safe without exposing
 * provider clients or persistence details to downstream application and message consumers.
 */
final readonly class AddressValidationVerdict implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $signal
     */
    public function __construct(
        public ?bool $deliverable,
        public ?string $granularity,
        public ?int $quality,
        /** @var array<string, mixed> */
        public array $signal = [],
    ) {
    }

    /**
     * Rehydrates a normalized validation verdict from the persisted or transported scalar payload.
     *
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): ?self
    {
        if (null === $data) {
            return null;
        }

        return new self(
            self::asNullableBool($data['deliverable'] ?? null),
            self::asNullableString($data['granularity'] ?? null),
            self::asQualityScore($data['quality'] ?? null),
            self::asSignal($data['signal'] ?? null),
        );
    }

    private static function asNullableBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return 1 === (int) $value;
        }
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower(trim($value))) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default => null,
        };
    }

    private static function asNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return '' === $normalized ? null : $normalized;
    }

    private static function asQualityScore(mixed $value): ?int
    {
        $quality = match (true) {
            is_int($value) => $value,
            is_float($value) => (int) round($value),
            is_string($value) && is_numeric($value) => (int) round((float) $value),
            default => null,
        };

        return null === $quality ? null : max(0, min(100, $quality));
    }

    /** @return array<string, mixed> */
    private static function asSignal(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * Serializes the normalized verdict into the stable Addressing message payload shape.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'deliverable' => $this->deliverable,
            'granularity' => $this->granularity,
            'quality' => $this->quality,
            'signal' => $this->signal,
        ];
    }
}
