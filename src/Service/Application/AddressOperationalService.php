<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Application;

use App\Addressing\RepositoryInterface\AddressOperationalRepositoryInterface;

/**
 * Applies operational metadata updates through the scoped Addressing repository boundary.
 */
final readonly class AddressOperationalService
{
    public function __construct(private AddressOperationalRepositoryInterface $addressOperationalRepository)
    {
    }

    /**
     * Applies a scoped operational-field patch to one address record.
     *
     * @param array<string, mixed> $patch
     */
    public function patchOperational(string $id, ?string $ownerId, ?string $vendorId, array $patch): bool
    {
        return $this->addressOperationalRepository->patchOperational($id, $ownerId, $vendorId, $patch);
    }
}
