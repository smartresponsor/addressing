<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Dto;

final class AddressManageDto
{
    public ?string $ownerId = null;
    public ?string $vendorId = null;
    public string $line1 = '';
    public ?string $line2 = null;
    public string $city = '';
    public ?string $region = null;
    public ?string $postalCode = null;
    public string $countryCode = 'US';
}
