<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\RepositoryInterface\Persistence;

use App\Addressing\EntityInterface\Record\AddressInterface;
use App\Addressing\Value\Persistence\AddressPageCriteria;

interface AddressReadRepositoryInterface
{
    public function get(string $id, ?string $ownerId, ?string $vendorId): ?AddressInterface;

    public function findByDedupeKey(string $dedupeKey): ?AddressInterface;

    /**
     * @return array{'items': list<AddressInterface>, 'nextCursor': ?string}
     */
    public function findPage(AddressPageCriteria $criteria): array;
}
