<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Value\Primitive;

final readonly class Postal implements \Stringable
{
    private string $value;

    public function __construct(string $input)
    {
        $this->value = self::norm($input);
    }

    public static function norm(string $input): string
    {
        $normalizedInput = strtoupper(trim($input));
        $filtered = preg_replace('/[^A-Z0-9- ]/', '', $normalizedInput);
        if (null === $filtered) {
            $filtered = $normalizedInput;
        }
        $normalized = preg_replace('/\s+/', ' ', $filtered);
        if (null === $normalized) {
            return $filtered;
        }

        return $normalized;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
