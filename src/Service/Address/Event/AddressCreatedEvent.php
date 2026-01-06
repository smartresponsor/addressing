<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\Service\Address\Event;

use App\ServiceInterface\Address\Event\AddressEventInterface;

final class AddressCreatedEvent implements AddressEventInterface
{
    private \DateTimeImmutable $at;

    public function __construct(
        public readonly string $line1,
        public readonly ?string $line2,
        public readonly string $city,
        public readonly string $region,
        public readonly string $postal,
        public readonly string $country
    ) {
        $this->at = new \DateTimeImmutable('now');
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->at;
    }

    public function name(): string
    {
        return 'address.created';
    }
}
