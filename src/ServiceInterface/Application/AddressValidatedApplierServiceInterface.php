<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\ServiceInterface\Application;

use App\Addressing\Contract\Message\AddressValidated;

interface AddressValidatedApplierServiceInterface
{
    public function apply(string $id, AddressValidated $addressValidated, ?string $ownerId = null, ?string $vendorId = null): void;
}
