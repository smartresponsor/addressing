<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Projection\AddressIndex;

use App\Addressing\Event\AddressCreatedEvent;
use App\Addressing\Event\AddressUpdatedEvent;
use App\Addressing\RepositoryInterface\AddressIndex\AddressIndexRepositoryInterface;
use App\Addressing\Service\Projection\AddressIndex\AddressIndexProjectorService;

final readonly class AddressIndexProjector
{
    public function __construct(
        private AddressIndexRepositoryInterface $addressIndexRepository,
        private AddressIndexNormalizer $addressIndexNormalizer,
        private AddressIndexProjectorService $addressIndexProjectorService,
    ) {
    }

    public function onAddressCreated(AddressCreatedEvent $addressCreatedEvent): void
    {
        $this->handle([
            'line1' => $addressCreatedEvent->line1,
            'line2' => $addressCreatedEvent->line2,
            'city' => $addressCreatedEvent->city,
            'region' => $addressCreatedEvent->region,
            'postal' => $addressCreatedEvent->postal,
            'country' => $addressCreatedEvent->country,
        ]);
    }

    public function onAddressUpdated(AddressUpdatedEvent $addressUpdatedEvent): void
    {
        $this->handle([
            'line1' => $addressUpdatedEvent->line1,
            'line2' => $addressUpdatedEvent->line2,
            'city' => $addressUpdatedEvent->city,
            'region' => $addressUpdatedEvent->region,
            'postal' => $addressUpdatedEvent->postal,
            'country' => $addressUpdatedEvent->country,
        ]);
    }

    /** @param array{line1: string, line2: ?string, city: string, region: string, postal: string, country: string} $payload */
    private function handle(array $payload): void
    {
        $norm = $this->addressIndexNormalizer->normalize($payload);
        $addressIndexRecord = $this->addressIndexProjectorService->project($norm);
        $this->addressIndexRepository->upsert($addressIndexRecord);
    }
}
