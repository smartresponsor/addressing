<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\EventInterface;

/**
 * Defines the immutable metadata shared by address lifecycle events emitted by Addressing.
 */
interface AddressEventInterface
{
    /**
     * Returns the instant at which the address lifecycle event was recorded.
     */
    public function occurredAt(): \DateTimeImmutable;

    /**
     * Returns the stable event name used by Addressing event consumers.
     */
    public function nameEntity(): string;
}
