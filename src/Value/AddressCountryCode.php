<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Value;

/**
 * Represents a normalized ISO 3166-1 alpha-2 country code value.
 */
final readonly class AddressCountryCode implements \Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        $value = strtoupper(trim($value));
        if (!preg_match('/^[A-Z]{2}$/', $value)) {
            throw new \InvalidArgumentException('CountryCode must be ISO 3166-1 alpha-2');
        }
        $this->value = $value;
    }

    /** Returns the normalized two-letter country code value. */
    public function value(): string
    {
        return $this->value;
    }

    /** Compares this normalized country code with another value object. */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
