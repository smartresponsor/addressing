<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

use App\Addressing\Contract\AddressInterface;

/**
 * Defines the command-side persistence contract for creating, updating, and deleting addresses.
 */
interface AddressWriteRepositoryInterface
{
    /** Persists a new canonical address record through the component write boundary. */
    public function create(AddressInterface $address): void;

    /** Persists state changes for an existing canonical address record. */
    public function update(AddressInterface $address): void;

    /** Deletes one address identified within the supplied ownership scope. */
    public function delete(string $id, ?string $ownerId, ?string $vendorId): void;
}
