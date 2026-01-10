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
final class Region
{
    private string $v;

    /**
     * @param string $s
     */
    public function __construct(string $s)
    {
        $this->v = strtoupper(trim($s));
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->v;
    }
}
