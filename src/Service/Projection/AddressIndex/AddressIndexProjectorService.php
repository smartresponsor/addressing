<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Projection\AddressIndex;

use App\Addressing\Integration\Geocode\AddressGeocodeResult;
use App\Addressing\Projection\AddressIndex\AddressIndexRecord;
use App\Addressing\Value\AddressCountryCode;
use App\Addressing\Value\AddressPostalCode;
use App\Addressing\Value\AddressStreetLine;
use App\Addressing\Value\Primitive\AddressRegion;

final class AddressIndexProjectorService
{
    /**
     * @param array{line1: AddressStreetLine, line2: ?AddressStreetLine, city: string, region: AddressRegion, postal: AddressPostalCode, country: AddressCountryCode, digest: string} $norm
     */
    public function project(array $norm, ?AddressGeocodeResult $geocodeResult = null): AddressIndexRecord
    {
        return AddressIndexRecord::fromNormalized($norm, $geocodeResult);
    }
}
