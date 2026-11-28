<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Value\Primitive;

/** Represents region text normalized by trimming and uppercasing. */
final readonly class AddressRegion implements \Stringable
{
    private string $value;

    /** @psalm-mutation-free */
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
