<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\Service\Address\Event;

use App\ServiceInterface\Address\Event\AddressEventInterface;
use DateTimeImmutable;

/**
 *
 */

/**
 *
 */
final class AddressUpdatedEvent implements AddressEventInterface
{
    private DateTimeImmutable $at;

    /**
     * @param string $line1
     * @param string|null $line2
     * @param string $city
     * @param string $region
     * @param string $postal
     * @param string $country
     */
    public function __construct(
        public readonly string  $line1,
        public readonly ?string $line2,
        public readonly string  $city,
        public readonly string  $region,
        public readonly string  $postal,
        public readonly string  $country
    )
    {
        $this->at = new DateTimeImmutable('now');
    }

    /**
     * @return \DateTimeImmutable
     */
    public function occurredAt(): DateTimeImmutable
    {
        return $this->at;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return 'address.updated';
    }
}
