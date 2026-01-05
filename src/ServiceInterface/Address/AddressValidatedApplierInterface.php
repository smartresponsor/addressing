<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

declare(strict_types=1);

namespace App\ServiceInterface\Address;

use App\Contract\Address\AddressValidated;

interface AddressValidatedApplierInterface
{
    public function apply(string $id, AddressValidated $validated): void;
}
