<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\RepositoryInterface\Persistence;

use App\EntityInterface\Record\AddressInterface;

interface AddressWriteRepositoryInterface
{
    public function create(AddressInterface $address): void;

    public function update(AddressInterface $address): void;

    public function delete(string $id, ?string $ownerId, ?string $vendorId): void;
}
