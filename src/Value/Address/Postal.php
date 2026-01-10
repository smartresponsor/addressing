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
final class Postal
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
        $s = strtoupper(trim($s));
        $s = preg_replace('/[^A-Z0-9- ]/', '', $s);
        return preg_replace('/\s+/', ' ', $s);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->v;
    }
}
