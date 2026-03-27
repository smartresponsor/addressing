<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\ServiceInterface\Application;

use App\Contract\Message\AddressValidated;

interface AddressValidatedApplierServiceInterface
{
    public function apply(string $id, AddressValidated $validated, ?string $ownerId = null, ?string $vendorId = null): void;
}
