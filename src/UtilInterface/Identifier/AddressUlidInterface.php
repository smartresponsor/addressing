<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\UtilInterface\Identifier;

interface AddressUlidInterface
{
    public static function generate(): string;
}
