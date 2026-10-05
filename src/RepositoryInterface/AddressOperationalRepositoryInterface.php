<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

/**
 * Applies scoped operational metadata changes to persisted address records without changing identity.
 */
interface AddressOperationalRepositoryInterface
{
    /**
     * Applies an operational-field patch when the addressed record exists within the supplied scope.
     *
     * @param array<string, mixed> $patch
     */
    public function patchOperational(string $id, ?string $ownerId, ?string $vendorId, array $patch): bool;
}
