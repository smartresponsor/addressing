<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Application\Event;

use App\ServiceInterface\Application\Event\AddressEventInterface;

final readonly class AddressCreatedEvent implements AddressEventInterface
{
    private \DateTimeImmutable $dateTimeImmutable;

    public function __construct(
        public string $line1,
        public ?string $line2,
        public string $city,
        public string $region,
        public string $postal,
        public string $country,
    ) {
        $this->dateTimeImmutable = new \DateTimeImmutable('now');
    }

    #[\Override]
    public function occurredAt(): \DateTimeImmutable
    {
        return $this->dateTimeImmutable;
    }

    #[\Override]
    public function name(): string
    {
        return 'address.created';
    }
}
