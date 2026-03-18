<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\Value\Primitive\Address;

final class Region
{
    private string $v;

    public function __construct(string $s)
    {
        $this->v = strtoupper(trim($s));
    }

    public function __toString(): string
    {
        return $this->v;
    }
}
