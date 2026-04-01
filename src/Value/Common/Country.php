<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Value\Common;

use InvalidArgumentException;

/**
 *
 */

/**
 *
 */
final readonly class Country implements \Stringable
{
    private string $code;

    public function __construct(string $alpha2)
    {
        $filtered = preg_replace('/[^A-Za-z]/', '', $alpha2);
        if ($filtered === null) {
            $filtered = '';
        }
        $c = strtoupper($filtered);
        if (strlen($c) !== 2) {
            throw new InvalidArgumentException('Country must be 2 letters');
        }
        $this->code = $c;
    }

    public static function from(?string $alpha2): self
    {
        return new self($alpha2 ?? 'US');
    }

    public function value(): string
    {
        return $this->code;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->code;
    }
}
