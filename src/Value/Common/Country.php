<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
namespace App\Value\Common;

final class Country
{
    private string $code;
    public function __construct(string $alpha2)
    {
        $c = strtoupper(preg_replace('/[^A-Za-z]/', '', $alpha2));
        if (strlen($c) !== 2) { throw new \InvalidArgumentException('Country must be 2 letters'); }
        $this->code = $c;
    }
    public static function from(?string $alpha2): self { return new self($alpha2 ?? 'US'); }
    public function value(): string { return $this->code; }
    public function __toString(): string { return $this->code; }
}
