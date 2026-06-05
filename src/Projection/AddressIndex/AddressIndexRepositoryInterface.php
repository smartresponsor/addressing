<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Projection\AddressIndex;

interface AddressIndexRepositoryInterface
{
    public function upsert(AddressIndexRecord $indexRecord): void;

    public function getByDigest(string $digest): ?AddressIndexRecord;

    /** @return AddressIndexRecord[] */
    public function search(string $prefix, ?string $country = null, int $limit = 20): array;
}
