<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AddressManageDto
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 256)]
    public string $line1 = '';

    #[Assert\Length(max: 256)]
    public ?string $line2 = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 128)]
    public string $city = '';

    #[Assert\Length(max: 32)]
    public ?string $region = null;

    #[Assert\Length(max: 32)]
    public ?string $postalCode = null;

    #[Assert\NotBlank]
    #[Assert\Regex('/^[A-Za-z]{2}$/')]
    public string $countryCode = 'US';

    #[Assert\Length(max: 64)]
    public ?string $ownerId = null;

    #[Assert\Length(max: 64)]
    public ?string $vendorId = null;
}
