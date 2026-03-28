<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Projection\AddressIndex;

use App\Service\Application\Event\AddressCreatedEvent;
use App\Service\Application\Event\AddressUpdatedEvent;

final readonly class IndexProjector
{
    public function __construct(
        private RepositoryInterface $addressIndexRepository,
        private Normalizer $normalizer,
    ) {
    }

    public function onAddressCreated(AddressCreatedEvent $addressCreatedEvent): void
    {
        $this->handle($addressCreatedEvent->line1, $addressCreatedEvent->line2, $addressCreatedEvent->city, $addressCreatedEvent->region, $addressCreatedEvent->postal, $addressCreatedEvent->country);
    }

    public function onAddressUpdated(AddressUpdatedEvent $addressUpdatedEvent): void
    {
        $this->handle($addressUpdatedEvent->line1, $addressUpdatedEvent->line2, $addressUpdatedEvent->city, $addressUpdatedEvent->region, $addressUpdatedEvent->postal, $addressUpdatedEvent->country);
    }

    private function handle(string $line1, ?string $line2, string $city, string $region, string $postal, string $country): void
    {
        $norm = $this->normalizer->normalize($line1, $line2, $city, $region, $postal, $country);
        $indexRecord = (new Projector())->project($norm);
        $this->addressIndexRepository->upsert($indexRecord);
    }
}
