<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Carries validated address-management form input across the standalone Addressing HTTP boundary.
 *
 * The DTO keeps user-entered address fields and optional ownership identifiers separate from persistence
 * entities so validation can complete before application services mutate Addressing state.
 */
final class AddressManageDTO
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
