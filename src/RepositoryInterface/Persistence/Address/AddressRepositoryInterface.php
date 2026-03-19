<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only.
 */
declare(strict_types=1);

namespace App\RepositoryInterface\Persistence\Address;

use App\EntityInterface\Record\Address\AddressInterface;

interface AddressRepositoryInterface
{
    public function create(AddressInterface $address): void;

    public function update(AddressInterface $address): void;

    public function get(string $id, ?string $ownerId, ?string $vendorId): ?AddressInterface;

    public function delete(string $id, ?string $ownerId, ?string $vendorId): void;

    public function findByDedupeKey(string $dedupeKey): ?AddressInterface;

    /**
     * @param array<string, mixed> $patch
     */
    public function patchOperational(string $id, ?string $ownerId, ?string $vendorId, array $patch): bool;

    /**
     * @return array{items: list<AddressInterface>, nextCursor: ?string}
     */
    public function findPage(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        int $limit,
        ?string $cursor,
        array $filters = [],
    ): array;
}
