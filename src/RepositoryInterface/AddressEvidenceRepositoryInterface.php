<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

use App\Addressing\Contract\AddressEvidenceSnapshotInterface;
use App\Addressing\Contract\AddressInterface;

/**
 * Defines persistence and paginated retrieval of immutable Addressing validation evidence snapshots.
 */
interface AddressEvidenceRepositoryInterface
{
    /** Persist the current address evidence snapshot when the address carries evidence. */
    public function appendEvidenceSnapshot(AddressInterface $address): ?AddressEvidenceSnapshotInterface;

    /** Return the most recent evidence snapshot for one address within the requested tenant scope. */
    public function getLatestEvidenceSnapshot(string $addressId, ?string $ownerId, ?string $vendorId): ?AddressEvidenceSnapshotInterface;

    /**
     * Return a cursor-paginated evidence history ordered newest-first within the requested tenant scope.
     *
     * @return array{'items': list<AddressEvidenceSnapshotInterface>, 'nextCursor': ?string}
     */
    public function findEvidenceHistoryPage(string $addressId, ?string $ownerId, ?string $vendorId, int $limit, ?string $cursor): array;
}
