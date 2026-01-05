<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 */
declare(strict_types=1);

namespace App\Value;

final class CountryCode
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

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
