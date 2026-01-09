<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
namespace App\Value\Address;

final class Line
{
    private string $v;
    public function __construct(string $s) { $this->v = self::norm($s); }
    public static function norm(string $s): string
    {
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return $s;
    }
    public static function normLocalized(string $s, string $locale): string
    {
        $s = self::norm($s);
        self::assertValidForLocale($s, $locale);
        return $s;
    }
    public static function isValidForLocale(string $s, string $locale): bool
    {
        $s = trim($s);
        if ($s === '') {
            return false;
        }
        return preg_match(LocaleRules::linePatternForLocale($locale), $s) === 1;
    }
    public static function assertValidForLocale(string $s, string $locale): void
    {
        if (!self::isValidForLocale($s, $locale)) {
            throw new \InvalidArgumentException('line_invalid_locale');
        }
    }
    public function __toString(): string { return $this->v; }
}
