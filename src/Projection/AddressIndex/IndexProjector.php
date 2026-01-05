<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
namespace App\Projection\AddressIndex;

use App\Service\Normalize\Normalizer;
use App\Integration\Geocode\GeocodeInterface;
use App\Integration\Geocode\GeocodeResult;
use App\Domain\Address\Event\AddressCreated;
use App\Domain\Address\Event\AddressUpdated;

final class IndexProjector
{
    public function __construct(
        private RepositoryInterface $repo,
        private Normalizer $normalizer,
        private ?GeocodeInterface $geocode = null,
        private bool $withNetwork = false
    ) { }

    public function onAddressCreated(AddressCreated $e): void
    {
        $this->handle($e->line1, $e->line2, $e->city, $e->region, $e->postal, $e->country);
    }

    public function onAddressUpdated(AddressUpdated $e): void
    {
        $this->handle($e->line1, $e->line2, $e->city, $e->region, $e->postal, $e->country);
    }

    private function handle(string $line1, ?string $line2, string $city, string $region, string $postal, string $country): void
    {
        $norm = $this->normalizer->normalize($line1, $line2, $city, $region, $postal, $country);
        $geo = null;
        if ($this->withNetwork && $this->geocode) {
            $geo = $this->geocode->forwardByParts((string)$norm['line1'], $norm['line2']? (string)$norm['line2'] : null, (string)$norm['city'], (string)$norm['region'], (string)$norm['postal'], $norm['country']->value(), 1)[0] ?? null;
        }
        $rec = (new Projector())->project($norm, $geo instanceof GeocodeResult ? $geo : null);
        $this->repo->upsert($rec);
    }
}
