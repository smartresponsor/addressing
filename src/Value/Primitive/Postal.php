<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Value\Primitive;

final class Postal
{
    private string $v;

    public function __construct(string $s)
    {
        $this->v = self::norm($s);
    }

    public static function norm(string $s): string
    {
        $s = strtoupper(trim($s));
        $filtered = preg_replace('/[^A-Z0-9- ]/', '', $s);
        if (null === $filtered) {
            $filtered = $s;
        }
        $normalized = preg_replace('/\s+/', ' ', $filtered);
        if (null === $normalized) {
            return $filtered;
        }

        return $normalized;
    }

    public function __toString(): string
    {
        return $this->v;
    }
}
