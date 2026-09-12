<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Value\Primitive;

final readonly class Line implements \Stringable
{
    private string $value;

    public function __construct(string $input)
    {
        $this->value = self::norm($input);
    }

    public static function norm(string $s): string
    {
        $normalized = preg_replace('/\s+/', ' ', $s);
        $normalized ??= $s;

        return trim($normalized);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
