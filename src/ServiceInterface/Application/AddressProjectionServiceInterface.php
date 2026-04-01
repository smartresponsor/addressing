<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\ServiceInterface\Application;

use App\EntityInterface\Record\AddressInterface;

interface AddressProjectionServiceInterface
{
    public function upsert(AddressInterface $address): void;
}
