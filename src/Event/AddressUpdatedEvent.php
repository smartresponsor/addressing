<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Event;

use App\Addressing\EventInterface\AddressEventInterface;

final readonly class AddressUpdatedEvent implements AddressEventInterface
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

    #[\Override]
    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    #[\Override]
    public function nameEntity(): string
    {
        return 'address.updated';
    }
}
