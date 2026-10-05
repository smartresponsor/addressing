<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

use App\Addressing\Contract\AddressInterface;
use App\Addressing\Value\Persistence\AddressPageCriteria;

/**
 * Provides scoped read access to canonical address records and deduplicated page results.
 */
interface AddressReadRepositoryInterface
{
    /** Loads one address by identifier when it is visible in the supplied ownership scope. */
    public function get(string $id, ?string $ownerId, ?string $vendorId): ?AddressInterface;

    /** Resolves the canonical address record associated with an exact persisted deduplication key. */
    public function findByDedupeKey(string $dedupeKey): ?AddressInterface;

    /**
     * Returns one cursor page of addresses matching the supplied persistence criteria.
     *
     * @return array{'items': list<AddressInterface>, 'nextCursor': ?string}
     */
    public function findPage(AddressPageCriteria $criteria): array;
}
