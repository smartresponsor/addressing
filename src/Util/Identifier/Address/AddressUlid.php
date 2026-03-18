<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Util\Identifier\Address;

use App\UtilInterface\Identifier\Address\AddressUlidInterface;

final class AddressUlid implements AddressUlidInterface
{
    public static function generate(): string
    {
        $milliseconds = (int) round(microtime(true) * 1000);
        $randomBytes = random_bytes(10);

        return self::base32FromInt($milliseconds).self::base32FromBinary($randomBytes);
    }

    private static function base32FromInt(int $value): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $encoded = '';

        while ($value > 0) {
            $encoded = $alphabet[$value % 32].$encoded;
            $value = intdiv($value, 32);
        }

        return str_pad($encoded, 10, '0', STR_PAD_LEFT);
    }

    private static function base32FromBinary(string $binary): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $bits = '';

        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        for ($index = 0; $index < 16; ++$index) {
            $chunk = substr($bits, $index * 5, 5);
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT)) % 32];
        }

        return $encoded;
    }
}
