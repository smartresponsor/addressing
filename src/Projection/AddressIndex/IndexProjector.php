<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\Projection\AddressIndex;

use App\Service\Address\Event\AddressCreatedEvent;
use App\Service\Address\Event\AddressUpdatedEvent;

/**
 *
 */

/**
 *
 */
final readonly class IndexProjector
{
    /**
     * @param \App\Projection\AddressIndex\RepositoryInterface $repo
     * @param \App\Projection\AddressIndex\Normalizer $normalizer
     */
    public function __construct(
        private RepositoryInterface $repo,
        private Normalizer          $normalizer
    )
    {
    }

    /**
     * @param \App\Service\Address\Event\AddressCreatedEvent $e
     * @return void
     */
    public function onAddressCreated(AddressCreatedEvent $e): void
    {
        $this->handle($e->line1, $e->line2, $e->city, $e->region, $e->postal, $e->country);
    }

    /**
     * @param \App\Service\Address\Event\AddressUpdatedEvent $e
     * @return void
     */
    public function onAddressUpdated(AddressUpdatedEvent $e): void
    {
        $this->handle($e->line1, $e->line2, $e->city, $e->region, $e->postal, $e->country);
    }

    /**
     * @param string $line1
     * @param string|null $line2
     * @param string $city
     * @param string $region
     * @param string $postal
     * @param string $country
     * @return void
     */
    private function handle(string $line1, ?string $line2, string $city, string $region, string $postal, string $country): void
    {
        $norm = $this->normalizer->normalize($line1, $line2, $city, $region, $postal, $country);
        $rec = (new Projector())->project($norm, null);
        $this->repo->upsert($rec);
    }
}
