<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\Value\Address;

/**
 *
 */

/**
 *
 */
final class Line
{
    private string $v;

    /**
     * @param string $s
     */
    public function __construct(string $s)
    {
        $this->v = self::norm($s);
    }

    /**
     * @param string $s
     * @return string
     */
    public static function norm(string $s): string
    {
        $normalized = preg_replace('/\s+/', ' ', $s);
        if ($normalized === null) {
            $normalized = $s;
        }
        return trim($normalized);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->v;
    }
}
