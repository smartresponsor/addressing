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

/**
 *
 */

/**
 *
 */
interface AddressRepositoryInterface
{
    /**
     * @param \App\EntityInterface\Address\AddressInterface $address
     * @return void
     */
    public function create(AddressInterface $address): void;

    /**
     * @param \App\EntityInterface\Address\AddressInterface $address
     * @return void
     */
    public function update(AddressInterface $address): void;

    /**
     * @param string $id
     * @return \App\EntityInterface\Address\AddressInterface|null
     */
    public function get(string $id): ?AddressInterface;

    /**
     * @param string $id
     * @return void
     */
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

    /**
     * @param string $addressId
     * @param array<string, string>|null $line1Localized
     * @param array<string, string>|null $cityLocalized
     * @return void
     */
    public function replaceLocalizations(string $addressId, ?array $line1Localized, ?array $cityLocalized): void;

    /**
     * @param string $addressId
     * @return array{line1Localized: ?array<string, string>, cityLocalized: ?array<string, string>}
     */
    public function fetchLocalizations(string $addressId): array;
}
