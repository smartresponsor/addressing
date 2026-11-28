<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Value;

/**
 * Represents a validated latitude and longitude pair with stable value semantics.
 */
final readonly class AddressGeoPoint implements \Stringable
{
    private float $lat;
    private float $lon;

    /** @psalm-mutation-free */
    public function __construct(float $lat, float $lon)
    {
        if (!is_finite($lat) || $lat < -90.0 || $lat > 90.0) {
            throw new \InvalidArgumentException('Latitude out of range');
        }
        if (!is_finite($lon) || $lon < -180.0 || $lon > 180.0) {
            throw new \InvalidArgumentException('Longitude out of range');
        }
        $this->lat = $lat;
        $this->lon = $lon;
    }

    /** Returns the validated latitude coordinate in decimal degrees. */
    public function lat(): float
    {
        return $this->lat;
    }

    /** Returns the validated longitude coordinate in decimal degrees. */
    public function lon(): float
    {
        return $this->lon;
    }

    /**
     * Compares both coordinates with another geographic point value object.
     *
     * @psalm-mutation-free
     */
    public function equals(self $other): bool
    {
        return $this->lat === $other->lat && $this->lon === $other->lon;
    }

    /**
     * Returns a stable fixed-precision coordinate key for indexing and comparison.
     *
     * @psalm-mutation-free
     */
    public function toKey(): string
    {
        return sprintf('%+.6f,%+.6f', $this->lat, $this->lon);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->toKey();
    }
}
