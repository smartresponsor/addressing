<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\ServiceInterface\Address;

use App\Contract\Address\AddressValidated;

interface AddressValidatedApplierInterface
{
    public function apply(string $id, AddressValidated $validated, ?string $ownerId = null, ?string $vendorId = null): void;
}
