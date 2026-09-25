<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Normalizer;

use App\Addressing\Value\AddressCountryCode;
use App\Addressing\Value\AddressPostalCode;
use App\Addressing\Value\AddressStreetLine;
use App\Addressing\Value\Primitive\AddressRegion;

final class AddressIndexNormalizer
{
    /**
     * @param array{line1: string, line2: ?string, city: string, region: string, postal: string, country: string} $address
     *
     * @return array{line1: AddressStreetLine, line2: ?AddressStreetLine, city: string, region: AddressRegion, postal: AddressPostalCode, country: AddressCountryCode, digest: string}
     */
    public function normalize(array $address): array
    {
        $line1Obj = new AddressStreetLine($address['line1']);
        $line2Obj = null;
        if (null !== $address['line2']) {
            $line2 = trim($address['line2']);
            if ('' !== $line2) {
                $line2Obj = new AddressStreetLine($line2);
            }
        }

        $city = trim($address['city']);
        if ('' === $city) {
            throw new \InvalidArgumentException('City is required');
        }

        $addressRegion = new AddressRegion($address['region']);
        $addressPostalCode = new AddressPostalCode($address['postal']);
        $addressCountryCode = new AddressCountryCode($address['country']);

        $digest = hash('sha256', implode('|', [
            $line1Obj->value(),
            $line2Obj?->value() ?? '',
            strtolower($city),
            (string) $addressRegion,
            $addressPostalCode->value(),
            $addressCountryCode->value(),
        ]));

        return [
            'line1' => $line1Obj,
            'line2' => $line2Obj,
            'city' => $city,
            'region' => $addressRegion,
            'postal' => $addressPostalCode,
            'country' => $addressCountryCode,
            'digest' => $digest,
        ];
    }
}
