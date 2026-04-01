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
final readonly class PostalCode implements \Stringable
{
    private string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if ($value === '' || strlen($value) < 3) {
            throw new InvalidArgumentException('PostalCode is too short');
        }
        if (strlen($value) > 32) {
            throw new InvalidArgumentException('PostalCode is too long');
        }
        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

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
