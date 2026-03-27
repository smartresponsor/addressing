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
final class PostalCode
{
    private string $value;

    /**
     * @param string $value
     */
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

    /**
     * @return string
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * @param \App\Value\PostalCode $other
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
