<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\RepositoryInterface\AddressIndex;

use App\Addressing\Projection\AddressIndex\AddressIndexRecord;

/**
 * Persists and queries normalized address-index projection records used by lookup workflows.
 */
interface AddressIndexRepositoryInterface
{
    /** Inserts or replaces the persisted projection represented by the supplied index record. */
    public function upsert(AddressIndexRecord $indexRecord): void;

    public function getByDigest(string $digest): ?AddressIndexRecord;

    /**
     * Searches indexed address records by normalized prefix with an optional country restriction.
     *
     * @return AddressIndexRecord[]
     */
    public function search(string $prefix, ?string $country = null, int $limit = 20): array;
}
