<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
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
    /**
     * @param \App\Projection\AddressIndex\IndexRecord $r
     * @return void
     */
    public function upsert(IndexRecord $r): void;

    /**
     * @param string $digest
     * @return \App\Projection\AddressIndex\IndexRecord|null
     */
    public function getByDigest(string $digest): ?IndexRecord;

    /** @return IndexRecord[] */
    public function search(string $prefix, ?string $country = null, int $limit = 20): array;
}
