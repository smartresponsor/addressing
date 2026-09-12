<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

use App\Addressing\EntityInterface\Record\AddressEvidenceSnapshotInterface;
use App\Addressing\EntityInterface\Record\AddressInterface;

interface AddressEvidenceRepositoryInterface
{
    public function appendEvidenceSnapshot(AddressInterface $address): ?AddressEvidenceSnapshotInterface;

    public function getLatestEvidenceSnapshot(string $addressId, ?string $ownerId, ?string $vendorId): ?AddressEvidenceSnapshotInterface;

    /**
     * @return array{'items': list<AddressEvidenceSnapshotInterface>, 'nextCursor': ?string}
     */
    public function findEvidenceHistoryPage(string $addressId, ?string $ownerId, ?string $vendorId, int $limit, ?string $cursor): array;
}
