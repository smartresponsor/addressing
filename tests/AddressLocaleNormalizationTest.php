<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

use App\Value\Address\Line;
use App\Value\Address\Postal;
use App\Value\Address\Region;
use PHPUnit\Framework\TestCase;

final class AddressLocaleNormalizationTest extends TestCase
{
    public function testLatinLocaleNormalization(): void
    {
        $line = Line::normLocalized(' 123 Main St. ', 'en-US');
        $this->assertSame('123 Main St.', $line);
        $this->assertTrue(Line::isValidForLocale($line, 'en-US'));

        $region = Region::normLocalized('ca', 'en-US');
        $this->assertSame('CA', $region);

        $postal = Postal::normLocalized(' 94105 ', 'en-US');
        $this->assertSame('94105', $postal);
    }

    public function testCyrillicLocaleNormalization(): void
    {
        $line = Line::normLocalized('ул. Крещатик 1', 'ru-RU');
        $this->assertSame('ул. Крещатик 1', $line);
        $this->assertTrue(Line::isValidForLocale($line, 'ru-RU'));

        $region = Region::normLocalized('Київська область', 'uk-UA');
        $this->assertSame('Київська область', $region);
    }

    public function testCjkLocaleNormalization(): void
    {
        $line = Line::normLocalized('東京都千代田区1-1', 'ja-JP');
        $this->assertSame('東京都千代田区1-1', $line);
        $this->assertTrue(Line::isValidForLocale($line, 'ja-JP'));

        $postal = Postal::normLocalized('100-0001', 'ja-JP');
        $this->assertSame('100-0001', $postal);
    }
}
