<?php

declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

interface AddressSchemaRepositoryInterface
{
    public function ensureSchema(): void;

    public function resetSchema(): void;
}
