<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
namespace App\Projection\AddressIndex;

use App\Integration\Geocode\GeocodeResult;

final class Projector
{
    /** @param array{line1:object,line2:?object,city:string,region:object,postal:object,country:object,digest:string} $norm */
    public function project(array $norm, ?GeocodeResult $geo = null): IndexRecord
    {
        return IndexRecord::fromNormalized($norm, $geo);
    }
}
