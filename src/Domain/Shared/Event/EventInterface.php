<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
namespace App\Domain\Shared\Event;

interface EventInterface
{
    public function occurredAt(): \DateTimeImmutable;
    public function name(): string;
}
