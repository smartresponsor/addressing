<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\RepositoryInterface\Persistence;

interface AddressOperationalRepositoryInterface
{
    /** @param array<string, mixed> $patch */
    public function patchOperational(string $id, ?string $ownerId, ?string $vendorId, array $patch): bool;
}
