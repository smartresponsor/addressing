<?php

declare(strict_types=1);

namespace App\Addressing\Service;

use App\Addressing\RepositoryInterface\AddressSchemaRepositoryInterface;

final readonly class AddressDoctrineSchemaManager
{
    public function __construct(private AddressSchemaRepositoryInterface $addressSchemaRepository)
    {
    }

    public function ensureSchema(): void
    {
        $this->addressSchemaRepository->ensureSchema();
    }

    public function resetSchema(): void
    {
        $this->addressSchemaRepository->resetSchema();
    }
}
