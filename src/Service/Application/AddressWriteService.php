<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Application;

use App\Addressing\Contract\AddressInterface;
use App\Addressing\RepositoryInterface\AddressWriteRepositoryInterface;

/**
 * Provides the application command surface for canonical address persistence operations.
 */
final readonly class AddressWriteService
{
    public function __construct(private AddressWriteRepositoryInterface $addressWriteRepository)
    {
    }

    /** Persists a newly created address through the command-side repository boundary. */
    public function create(AddressInterface $address): void
    {
        $this->addressWriteRepository->create($address);
    }

    /** Persists modifications to an existing canonical address record. */
    public function update(AddressInterface $address): void
    {
        $this->addressWriteRepository->update($address);
    }

    /** Removes one address identified within the supplied ownership scope. */
    public function markDeleted(string $id, ?string $ownerId, ?string $vendorId): void
    {
        $this->addressWriteRepository->delete($id, $ownerId, $vendorId);
    }
}
