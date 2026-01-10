<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
namespace App\Projection\AddressIndex;

use App\Integration\Geocode\GeocodeResult;
use App\Value\Address\Region;
use App\Value\CountryCode;
use App\Value\PostalCode;
use App\Value\StreetLine;

/**
 *
 */

/**
 *
 */
final class Projector
{
    /**
     * @param array{line1: StreetLine, line2: ?StreetLine, city: string, region: Region, postal: PostalCode, country: CountryCode, digest: string} $norm
     */
    public function project(array $norm, ?GeocodeResult $geo = null): IndexRecord
    {
        return IndexRecord::fromNormalized($norm, $geo);
    }
}
