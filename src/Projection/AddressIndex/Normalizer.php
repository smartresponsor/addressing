<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\Projection\AddressIndex;

use App\Value\Address\Region;
use App\Value\CountryCode;
use App\Value\PostalCode;
use App\Value\StreetLine;
use InvalidArgumentException;

/**
 *
 */

/**
 *
 */
final class Normalizer
{
    /**
     * @param string $line1
     * @param string|null $line2
     * @param string $city
     * @param string $region
     * @param string $postal
     * @param string $country
     * @return array{line1: StreetLine, line2: ?StreetLine, city: string, region: Region, postal: PostalCode, country: CountryCode, digest: string}
     */
    public function normalize(
        string $line1,
        ?string $line2,
        string $city,
        string $region,
        string $postal,
        string $country
    ): array {
        $line1Obj = new StreetLine($line1);
        $line2Obj = null;
        if ($line2 !== null) {
            $line2 = trim($line2);
            if ($line2 !== '') {
                $line2Obj = new StreetLine($line2);
            }
        }

        $city = trim($city);
        if ($city === '') {
            throw new InvalidArgumentException('City is required');
        }

        $regionObj = new Region($region);
        $postalObj = new PostalCode($postal);
        $countryObj = new CountryCode($country);

        $digest = hash('sha256', implode('|', [
            $line1Obj->value(),
            $line2Obj?->value() ?? '',
            strtolower($city),
            (string) $regionObj,
            $postalObj->value(),
            $countryObj->value(),
        ]));

        return [
            'line1' => $line1Obj,
            'line2' => $line2Obj,
            'city' => $city,
            'region' => $regionObj,
            'postal' => $postalObj,
            'country' => $countryObj,
            'digest' => $digest,
        ];
    }
}
