<?php
declare(strict_types=1);

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

namespace App\Service\Address;

use App\EntityInterface\Address\AddressInterface;
use App\RepositoryInterface\Address\AddressRepositoryInterface;

final class AddressService
{
    public function __construct(private readonly AddressRepositoryInterface $repo)
    {
    }

    public function create(AddressInterface $address): void
    {
        $this->repo->create($address);
    }

    public function update(AddressInterface $address): void
    {
        $this->repo->update($address);
    }

    /**
     * @return array{items: list<AddressInterface>, nextCursor: ?string}
     */
    public function search(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        int     $limit,
        ?string $cursor
    ): array
    {
        return $this->repo->findPage($ownerId, $vendorId, $countryCode, $q, $limit, $cursor);
    }

    public function dedupe(?string $dedupeKey): ?AddressInterface
    {
        if ($dedupeKey === null) {
            return null;
        }

        return $this->repo->findByDedupeKey($dedupeKey);
    }

    public function get(string $id, ?string $ownerId, ?string $vendorId): ?AddressInterface
    {
        return $this->repo->get($id, $ownerId, $vendorId);
    }
}
