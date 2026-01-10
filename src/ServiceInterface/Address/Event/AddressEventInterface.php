<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\ServiceInterface\Address\Event;

use DateTimeImmutable;

/**
 *
 */

/**
 *
 */
interface AddressEventInterface
{
    /**
     * @return \DateTimeImmutable
     */
    public function occurredAt(): DateTimeImmutable;

    /**
     * @return string
     */
    public function name(): string;
}
