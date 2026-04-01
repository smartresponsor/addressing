<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Value\Primitive;

final readonly class Line implements \Stringable
{
    private string $v;

    public function __construct(string $s)
    {
        $this->v = self::norm($s);
    }

    public static function norm(string $s): string
    {
        $normalized = preg_replace('/\s+/', ' ', $s);
        if (null === $normalized) {
            $normalized = $s;
        }

        return trim($normalized);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->v;
    }
}
