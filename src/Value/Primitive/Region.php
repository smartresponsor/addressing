<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Value\Primitive;

final readonly class Region implements \Stringable
{
    private string $value;

    public function __construct(string $input)
    {
        $this->value = strtoupper(trim($input));
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
