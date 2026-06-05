<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Application;

use App\RepositoryInterface\Persistence\AddressOperationalRepositoryInterface;

final readonly class AddressOperationalService
{
    public function __construct(private AddressOperationalRepositoryInterface $operationalRepository)
    {
    }

    /** @param array<string, mixed> $patch */
    public function patchOperational(string $id, ?string $ownerId, ?string $vendorId, array $patch): bool
    {
        return $this->operationalRepository->patchOperational($id, $ownerId, $vendorId, $patch);
    }
}
