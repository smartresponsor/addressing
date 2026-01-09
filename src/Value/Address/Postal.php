<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
namespace App\Value\Address;

final class Postal
{
    private string $v;
    public function __construct(string $s) { $this->v = self::norm($s); }
    public static function norm(string $s): string
    {
        $s = strtoupper(trim($s));
        $s = preg_replace('/[^A-Z0-9- ]/', '', $s);
        return preg_replace('/\s+/', ' ', $s);
    }
    public static function normLocalized(string $s, string $locale): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        if (LocaleRules::scriptForLocale($locale) === 'latin') {
            $s = strtoupper($s);
        }
        self::assertValidForLocale($s, $locale);
        return $s;
    }
    public static function isValidForLocale(string $s, string $locale): bool
    {
        $s = trim($s);
        if ($s === '') {
            return false;
        }
        return preg_match(LocaleRules::postalPatternForLocale($locale), $s) === 1;
    }
    public static function assertValidForLocale(string $s, string $locale): void
    {
        if (!self::isValidForLocale($s, $locale)) {
            throw new \InvalidArgumentException('postal_invalid_locale');
        }
    }
    public function __toString(): string { return $this->v; }
}
