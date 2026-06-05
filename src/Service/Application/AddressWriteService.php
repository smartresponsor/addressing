<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Application;

use App\EntityInterface\Record\AddressInterface;
use App\RepositoryInterface\Persistence\AddressWriteRepositoryInterface;

final readonly class AddressWriteService
{
    public function __construct(private AddressWriteRepositoryInterface $writeRepository)
    {
    }

    public function create(AddressInterface $address): void
    {
        $this->writeRepository->create($address);
    }

    public function update(AddressInterface $address): void
    {
        $this->writeRepository->update($address);
    }

    public function markDeleted(string $id, ?string $ownerId, ?string $vendorId): void
    {
        $this->writeRepository->delete($id, $ownerId, $vendorId);
    }
}
