<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 *
 * This file is part of SmartResponsor Address data domain.
 * Comments are in English only. No placeholders, no stubs.
 */

namespace App\Layer\Address;

use App\UtilInterface\Address\AddressUlidInterface;

/**
 *
 */

/**
 *
 */
final class AddressUlid implements AddressUlidInterface
{
    /**
     * @return string
     * @throws \Exception
     */
    public static function generate(): string
    {
        // Simple Crockford Base32 without hyphens, 26 chars.
        $time = microtime(true);
        $ms = (int)round($time * 1000);
        $random = random_bytes(10);
        $timePart = self::base32($ms);
        $randPart = self::base32FromBinary($random);
        return $timePart . $randPart;
    }

    /**
     * @param int $value
     * @return string
     */
    private static function base32(int $value): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $res = '';
        while ($value > 0) {
            $res = $alphabet[$value % 32] . $res;
            $value = intdiv($value, 32);
        }
        return str_pad($res, 10, '0', STR_PAD_LEFT);
    }

    /**
     * @param string $bin
     * @return string
     */
    private static function base32FromBinary(string $bin): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $res = '';
        for ($i = 0; $i < 16; $i++) {
            $chunk = substr($bits, $i * 5, 5);
            $idx = bindec(str_pad($chunk, 5, '0'));
            $res .= $alphabet[$idx % 32];
        }
        return $res;
    }
}
