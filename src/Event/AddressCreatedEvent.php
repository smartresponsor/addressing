<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Event;

use App\Addressing\EventInterface\AddressEventInterface;

/**
 * Captures the normalized address payload published when an address is created.
 */
final readonly class AddressCreatedEvent implements AddressEventInterface
{
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        public string $line1,
        public ?string $line2,
        public string $city,
        public string $region,
        public string $postal,
        public string $country,
    ) {
        $this->occurredAt = new \DateTimeImmutable('now');
    }

    /**
     * Returns the creation event timestamp captured when this event was instantiated.
     */
    #[\Override]
    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * Returns the stable event discriminator for newly created addresses.
     */
    #[\Override]
    public function nameEntity(): string
    {
        return 'address.created';
    }
}
