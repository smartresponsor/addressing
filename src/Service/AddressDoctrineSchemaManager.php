<?php

declare(strict_types=1);

namespace App\Addressing\Service;

use App\Addressing\RepositoryInterface\AddressSchemaRepositoryInterface;

/**
 * Coordinates Addressing schema lifecycle operations through the repository-owned persistence boundary.
 */
final readonly class AddressDoctrineSchemaManager
{
    public function __construct(private AddressSchemaRepositoryInterface $addressSchemaRepository)
    {
    }

    /** Ensures the Addressing persistence schema is available before repository operations execute. */
    public function ensureSchema(): void
    {
        $this->addressSchemaRepository->ensureSchema();
    }

    /** Resets the Addressing persistence schema through the canonical repository abstraction. */
    public function resetSchema(): void
    {
        $this->addressSchemaRepository->resetSchema();
    }
}
