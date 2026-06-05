<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Projection\AddressIndex;

use App\Value\CountryCode;
use App\Value\PostalCode;
use App\Value\Primitive\Region;
use App\Value\StreetLine;

final class AddressIndexNormalizer
{
    /**
     * @param array{line1: string, line2: ?string, city: string, region: string, postal: string, country: string} $address
     *
     * @return array{line1: StreetLine, line2: ?StreetLine, city: string, region: Region, postal: PostalCode, country: CountryCode, digest: string}
     */
    public function normalize(array $address): array
    {
        $line1Obj = new StreetLine($address['line1']);
        $line2Obj = null;
        if (null !== $address['line2']) {
            $line2 = trim($address['line2']);
            if ('' !== $line2) {
                $line2Obj = new StreetLine($line2);
            }
        }

        $city = trim($address['city']);
        if ('' === $city) {
            throw new \InvalidArgumentException('City is required');
        }

        $regionObj = new Region($address['region']);
        $postalCode = new PostalCode($address['postal']);
        $countryCode = new CountryCode($address['country']);

        $digest = hash('sha256', implode('|', [
            $line1Obj->value(),
            $line2Obj?->value() ?? '',
            strtolower($city),
            (string) $regionObj,
            $postalCode->value(),
            $countryCode->value(),
        ]));

        return [
            'line1' => $line1Obj,
            'line2' => $line2Obj,
            'city' => $city,
            'region' => $regionObj,
            'postal' => $postalCode,
            'country' => $countryCode,
            'digest' => $digest,
        ];
    }
}
