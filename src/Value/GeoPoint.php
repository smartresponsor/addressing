<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Value;

use InvalidArgumentException;

/**
 *
 */

/**
 *
 */
final class GeoPoint
{
    private float $lat;
    private float $lon;

    /**
     * @param float $lat
     * @param float $lon
     */
    public function __construct(float $lat, float $lon)
    {
        if ($lat < -90.0 || $lat > 90.0) {
            throw new InvalidArgumentException('Latitude out of range');
        }
        if ($lon < -180.0 || $lon > 180.0) {
            throw new InvalidArgumentException('Longitude out of range');
        }
        $this->lat = $lat;
        $this->lon = $lon;
    }

    /**
     * @return float
     */
    public function lat(): float
    {
        return $this->lat;
    }

    /**
     * @return float
     */
    public function lon(): float
    {
        return $this->lon;
    }

    /**
     * @param \App\Value\GeoPoint $other
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->lat === $other->lat && $this->lon === $other->lon;
    }

    /**
     * @return string
     */
    public function toKey(): string
    {
        return sprintf('%+.6f,%+.6f', $this->lat, $this->lon);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->toKey();
    }
}
