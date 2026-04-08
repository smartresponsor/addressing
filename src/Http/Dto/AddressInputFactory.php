<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Dto;

use App\Contract\Message\AddressRecordPolicy;
use App\Entity\Record\AddressData;
use App\Value\CountryCode;
use App\Value\PostalCode;
use App\Value\StreetLine;
use App\Value\Subdivision;
use DateTimeImmutable;
use Symfony\Component\Uid\Ulid;

final class AddressInputFactory
{
    /**
     * @param array<string, mixed> $overrides
     */
    public function fromManageDto(AddressManageDto $address_manage_dto, array $overrides = []): AddressData
    {
        $now = $this->stringOverride($overrides, 'createdAt') ?? (new DateTimeImmutable())->format('Y-m-d H:i:sP');
        $line1 = (string) new StreetLine($address_manage_dto->line1);
        $country_code = (string) new CountryCode($address_manage_dto->countryCode);
        $postal_code = null;
        if (null !== $address_manage_dto->postalCode && '' !== trim($address_manage_dto->postalCode)) {
            $postal_code = (string) new PostalCode($address_manage_dto->postalCode);
        }

        $region = null;
        if (null !== $address_manage_dto->region && '' !== trim($address_manage_dto->region)) {
            $region = (string) new Subdivision($address_manage_dto->region);
        }

        $city = trim($address_manage_dto->city);
        $owner_id = $this->nullableTrimmed($address_manage_dto->ownerId);
        $vendor_id = $this->nullableTrimmed($address_manage_dto->vendorId);
        $line2 = $this->nullableTrimmed($address_manage_dto->line2);

        $line1_norm = strtolower($line1);
        $city_norm = strtolower($city);
        $region_norm = null !== $region ? strtolower($region) : null;
        $postal_norm = null !== $postal_code ? strtolower(str_replace(' ', '', $postal_code)) : null;
        $dedupe_key = implode('|', array_filter([
            $line1_norm,
            $city_norm,
            $region_norm,
            $postal_norm,
            strtolower($country_code),
            $owner_id,
            $vendor_id,
        ], static fn (?string $value): bool => null !== $value && '' !== $value));

        return new AddressData(
            $this->stringOverride($overrides, 'id') ?? (string) new Ulid(),
            $owner_id,
            $vendor_id,
            $line1,
            $line2,
            $city,
            $region,
            $postal_code,
            $country_code,
            $line1_norm,
            $city_norm,
            $region_norm,
            $postal_norm,
            $this->floatOverride($overrides, 'latitude'),
            $this->floatOverride($overrides, 'longitude'),
            $this->stringOverride($overrides, 'geohash'),
            AddressRecordPolicy::normalizeValidationStatus($this->stringOverride($overrides, 'validationStatus'), 'pending'),
            $this->stringOverride($overrides, 'validationProvider'),
            $this->stringOverride($overrides, 'validatedAt'),
            '' !== $dedupe_key ? $dedupe_key : null,
            $now,
            $this->stringOverride($overrides, 'updatedAt'),
            $this->stringOverride($overrides, 'deletedAt'),
            $this->stringOverride($overrides, 'validationFingerprint'),
            isset($overrides['validationRaw']) && is_array($overrides['validationRaw']) ? $overrides['validationRaw'] : null,
            isset($overrides['validationVerdict']) && is_array($overrides['validationVerdict']) ? $overrides['validationVerdict'] : null,
            $this->boolOverride($overrides, 'validationDeliverable'),
            $this->stringOverride($overrides, 'validationGranularity'),
            $this->intOverride($overrides, 'validationQuality'),
            $this->stringOverride($overrides, 'sourceSystem') ?? 'symfony-demo',
            AddressRecordPolicy::normalizeSourceType($this->stringOverride($overrides, 'sourceType') ?? 'manual'),
            $this->stringOverride($overrides, 'sourceReference'),
            $this->stringOverride($overrides, 'normalizationVersion') ?? 'demo-v1',
            isset($overrides['rawInputSnapshot']) && is_array($overrides['rawInputSnapshot']) ? $overrides['rawInputSnapshot'] : [
                'line1' => $line1,
                'line2' => $line2,
                'city' => $city,
                'region' => $region,
                'postalCode' => $postal_code,
                'countryCode' => $country_code,
            ],
            isset($overrides['normalizedSnapshot']) && is_array($overrides['normalizedSnapshot']) ? $overrides['normalizedSnapshot'] : [
                'line1Norm' => $line1_norm,
                'cityNorm' => $city_norm,
                'regionNorm' => $region_norm,
                'postalCodeNorm' => $postal_norm,
            ],
            $this->stringOverride($overrides, 'providerDigest') ?? 'sha256:'.hash('sha256', $line1.'|'.$city.'|'.$country_code),
            AddressRecordPolicy::normalizeGovernanceStatus($this->stringOverride($overrides, 'governanceStatus') ?? 'canonical'),
            $this->stringOverride($overrides, 'duplicateOfId'),
            $this->stringOverride($overrides, 'supersededById'),
            $this->stringOverride($overrides, 'aliasOfId'),
            $this->stringOverride($overrides, 'conflictWithId'),
            $this->stringOverride($overrides, 'revalidationDueAt'),
            AddressRecordPolicy::normalizeRevalidationPolicy($this->stringOverride($overrides, 'revalidationPolicy') ?? 'quarterly'),
            $this->stringOverride($overrides, 'lastValidationProvider'),
            AddressRecordPolicy::normalizeLastValidationStatus($this->stringOverride($overrides, 'lastValidationStatus')),
            $this->intOverride($overrides, 'lastValidationScore'),
        );
    }

    /**
     * Resolves a string override when the provided value is a string.
     *
     * @param array<string, mixed> $overrides
     */
    private function stringOverride(array $overrides, string $key): ?string
    {
        return isset($overrides[$key]) && is_string($overrides[$key]) ? $overrides[$key] : null;
    }

    /**
     * Resolves an integer override.
     *
     * @param array<string, mixed> $overrides
     */
    private function intOverride(array $overrides, string $key): ?int
    {
        if (!isset($overrides[$key])) {
            return null;
        }

        $value = $overrides[$key];
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Resolves a float override from int or float input.
     *
     * @param array<string, mixed> $overrides
     */
    private function floatOverride(array $overrides, string $key): ?float
    {
        if (!isset($overrides[$key])) {
            return null;
        }

        $value = $overrides[$key];
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * Resolves a boolean override.
     *
     * @param array<string, mixed> $overrides
     */
    private function boolOverride(array $overrides, string $key): ?bool
    {
        return isset($overrides[$key]) && is_bool($overrides[$key]) ? $overrides[$key] : null;
    }

    /**
     * Trims a nullable string and returns null for empty values.
     */
    private function nullableTrimmed(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
