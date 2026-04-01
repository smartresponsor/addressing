<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Value;

use InvalidArgumentException;

/**
 *
 */

/**
 *
 */
final readonly class Subdivision implements \Stringable
{
    private string $code;

    public function __construct(string $code)
    {
        $code = strtoupper(trim($code));
        if ($code === '' || strlen($code) > 32) {
            throw new InvalidArgumentException('Subdivision code is invalid');
        }
        $this->code = $code;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->code;
    }
}
