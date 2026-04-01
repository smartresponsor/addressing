<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Projection\AddressIndex;

/**
 *
 */

/**
 *
 */
interface RepositoryInterface
{
    public function upsert(IndexRecord $indexRecord): void;

    public function getByDigest(string $digest): ?IndexRecord;

    /** @return IndexRecord[] */
    public function search(string $prefix, ?string $country = null, int $limit = 20): array;
}
