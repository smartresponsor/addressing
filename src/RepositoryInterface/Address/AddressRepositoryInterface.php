<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only.
 */
declare(strict_types=1);

namespace App\RepositoryInterface\Address;

use App\EntityInterface\Address\AddressInterface;

interface AddressRepositoryInterface
{
    public function create(AddressInterface $address): void;

    public function update(AddressInterface $address): void;

    public function get(string $id): ?AddressInterface;

    public function delete(string $id): void;

    /**
     * @return array{items: list<AddressInterface>, nextCursor: ?string}
     */
    public function findPage(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        int $limit,
        ?string $cursor
    ): array;
}
