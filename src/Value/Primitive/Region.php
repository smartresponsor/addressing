<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Value\Primitive;

final readonly class Region implements \Stringable
{
    private string $v;

    public function __construct(string $s)
    {
        $this->v = strtoupper(trim($s));
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->v;
    }
}
