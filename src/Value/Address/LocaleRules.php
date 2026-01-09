<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */
declare(strict_types=1);

namespace App\Value\Address;

final class LocaleRules
{
    public static function scriptForLocale(string $locale): string
    {
        $locale = strtolower(str_replace('_', '-', $locale));
        if (str_starts_with($locale, 'ru')
            || str_starts_with($locale, 'uk')
            || str_starts_with($locale, 'bg')
            || str_starts_with($locale, 'sr')
        ) {
            return 'cyrillic';
        }
        if (str_starts_with($locale, 'ja')
            || str_starts_with($locale, 'zh')
            || str_starts_with($locale, 'ko')
        ) {
            return 'cjk';
        }

        return 'latin';
    }

    public static function linePatternForLocale(string $locale): string
    {
        return match (self::scriptForLocale($locale)) {
            'cyrillic' => '/^[\p{Cyrillic}\p{N}\p{Zs}\p{P}\p{S}]+$/u',
            'cjk' => '/^[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{N}\p{Zs}\p{P}\p{S}]+$/u',
            default => '/^[\p{Latin}\p{N}\p{Zs}\p{P}\p{S}]+$/u',
        };
    }

    public static function regionPatternForLocale(string $locale): string
    {
        return self::linePatternForLocale($locale);
    }

    public static function postalPatternForLocale(string $locale): string
    {
        return match (self::scriptForLocale($locale)) {
            'cyrillic' => '/^[\p{Cyrillic}\p{N}\p{Zs}-]+$/u',
            'cjk' => '/^[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{N}\p{Zs}-]+$/u',
            default => '/^[A-Z0-9 -]+$/u',
        };
    }
}
