<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Value;

/** Represents a trimmed street line subject to the component's length bounds. */
final readonly class AddressStreetLine implements \Stringable
{
    private string $value;

    /** @psalm-mutation-free */
    public function __construct(string $value)
    {
        $value = trim($value);
        if ('' === $value || strlen($value) < 2) {
            throw new \InvalidArgumentException('StreetLine is too short');
        }
        if (strlen($value) > 256) {
            throw new \InvalidArgumentException('StreetLine is too long');
        }
        $this->value = $value;
    }

    /** Returns the validated street line without surrounding whitespace. */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Compares the normalized stored street lines for exact equality.
     *
     * @psalm-mutation-free
     */
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
