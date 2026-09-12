<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Application;

use App\Addressing\EntityInterface\Record\AddressInterface;
use App\Addressing\RepositoryInterface\AddressWriteRepositoryInterface;

final readonly class AddressWriteService
{
    public function __construct(private AddressWriteRepositoryInterface $addressWriteRepository)
    {
    }

    public function create(AddressInterface $address): void
    {
        $this->addressWriteRepository->create($address);
    }

    public function update(AddressInterface $address): void
    {
        $this->addressWriteRepository->update($address);
    }

    public function markDeleted(string $id, ?string $ownerId, ?string $vendorId): void
    {
        $this->addressWriteRepository->delete($id, $ownerId, $vendorId);
    }
}
