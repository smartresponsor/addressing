<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\FactoryInterface\Identifier;

/**
 * Defines the Addressing contract for generating canonical string identifiers.
 *
 * @psalm-mutable
 */
interface AddressUlidInterface
{
    /**
     * Creates a new canonical Addressing identifier suitable for persistence and transport boundaries.
     *
     * @psalm-impure
     */
    public static function generate(): string;
}
