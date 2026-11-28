<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Value;

/** Represents a trimmed, uppercase subdivision code with bounded length. */
final readonly class AddressSubdivision implements \Stringable
{
    private string $code;

    /** @psalm-mutation-free */
    public function __construct(string $code)
    {
        $code = strtoupper(trim($code));
        if ('' === $code || strlen($code) > 32) {
            throw new \InvalidArgumentException('Subdivision code is invalid');
        }
        $this->code = $code;
    }

    /** Returns the normalized subdivision code. */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * Compares normalized subdivision codes for exact equality.
     *
     * @psalm-mutation-free
     */
    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->code;
    }
}
