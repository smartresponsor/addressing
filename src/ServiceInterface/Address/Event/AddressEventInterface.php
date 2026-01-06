<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\ServiceInterface\Address\Event;

interface AddressEventInterface
{
    public function occurredAt(): \DateTimeImmutable;

    public function name(): string;
}
