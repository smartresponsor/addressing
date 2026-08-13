<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Projection\AddressIndex;

use App\Addressing\Integration\Geocode\GeocodeResult;
use App\Addressing\Value\CountryCode;
use App\Addressing\Value\PostalCode;
use App\Addressing\Value\Primitive\Region;
use App\Addressing\Value\StreetLine;

final class AddressIndexProjectorService
{
    /**
     * @param array{line1: StreetLine, line2: ?StreetLine, city: string, region: Region, postal: PostalCode, country: CountryCode, digest: string} $norm
     */
    public function project(array $norm, ?GeocodeResult $geocodeResult = null): AddressIndexRecord
    {
        return AddressIndexRecord::fromNormalized($norm, $geocodeResult);
    }
}
