<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Projection\AddressIndex;

use App\Addressing\Projection\AddressIndex\AddressIndexRecord;
use App\Addressing\Value\AddressCountryCode;
use App\Addressing\Value\AddressPostalCode;
use App\Addressing\Value\AddressStreetLine;
use App\Addressing\Value\Geocode\AddressGeocodeResult;
use App\Addressing\Value\Primitive\AddressRegion;

/**
 * Projects normalized address data into immutable address-index records for persistence.
 */
final class AddressIndexProjectorService
{
    /**
     * Builds an address-index record from normalized address fields and optional geocoding evidence.
     *
     * @param array{line1: AddressStreetLine, line2: ?AddressStreetLine, city: string, region: AddressRegion, postal: AddressPostalCode, country: AddressCountryCode, digest: string} $norm
     */
    public function project(array $norm, ?AddressGeocodeResult $geocodeResult = null): AddressIndexRecord
    {
        return AddressIndexRecord::fromNormalized($norm, $geocodeResult);
    }
}
