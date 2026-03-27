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
final class Subdivision
{
    private string $code;

    /**
     * @param string $code
     */
    public function __construct(string $code)
    {
        $code = strtoupper(trim($code));
        if ($code === '' || strlen($code) > 32) {
            throw new InvalidArgumentException('Subdivision code is invalid');
        }
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * @param \App\Value\Subdivision $other
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->code;
    }
}
