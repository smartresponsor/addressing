<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Projection\AddressIndex;

use App\Service\Application\Event\AddressCreatedEvent;
use App\Service\Application\Event\AddressUpdatedEvent;

final readonly class IndexProjector
{
    public function __construct(
        private RepositoryInterface $repo,
        private Normalizer $normalizer,
    ) {
    }

    public function onAddressCreated(AddressCreatedEvent $e): void
    {
        $this->handle($e->line1, $e->line2, $e->city, $e->region, $e->postal, $e->country);
    }

    public function onAddressUpdated(AddressUpdatedEvent $e): void
    {
        $this->handle($e->line1, $e->line2, $e->city, $e->region, $e->postal, $e->country);
    }

    private function handle(string $line1, ?string $line2, string $city, string $region, string $postal, string $country): void
    {
        $norm = $this->normalizer->normalize($line1, $line2, $city, $region, $postal, $country);
        $rec = (new Projector())->project($norm);
        $this->repo->upsert($rec);
    }
}
