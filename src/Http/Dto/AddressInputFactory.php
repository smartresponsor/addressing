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
use Symfony\Component\Uid\Ulid;

final class AddressInputFactory
{
    /**
     * @param array<string, mixed> $overrides
     */
    public function fromManageDto(AddressManageDto $addressManageDto, array $overrides = []): AddressData
    {
        $now = $this->stringOverride($overrides, 'createdAt') ?? (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');
        $line1 = (string) new StreetLine($addressManageDto->line1);
        $countryCode = (string) new CountryCode($addressManageDto->countryCode);
        $postalCode = null;
        if (null !== $addressManageDto->postalCode && '' !== trim($addressManageDto->postalCode)) {
            $postalCode = (string) new PostalCode($addressManageDto->postalCode);
        }

        $region = null;
        if (null !== $addressManageDto->region && '' !== trim($addressManageDto->region)) {
            $region = (string) new Subdivision($addressManageDto->region);
        }

        $city = trim($addressManageDto->city);
        $ownerId = $this->nullableTrimmed($addressManageDto->ownerId);
        $vendorId = $this->nullableTrimmed($addressManageDto->vendorId);
        $line2 = $this->nullableTrimmed($addressManageDto->line2);

        $line1Norm = strtolower($line1);
        $cityNorm = strtolower($city);
        $regionNorm = null !== $region ? strtolower($region) : null;
        $postalNorm = null !== $postalCode ? strtolower(str_replace(' ', '', $postalCode)) : null;
        $dedupeKey = implode('|', array_filter([
            $line1Norm,
            $cityNorm,
            $regionNorm,
            $postalNorm,
            strtolower($countryCode),
            $ownerId,
            $vendorId,
        ], static fn (?string $value): bool => null !== $value && '' !== $value));

        return new AddressData(
            $this->stringOverride($overrides, 'id') ?? (string) new Ulid(),
            $ownerId,
            $vendorId,
            $line1,
            $line2,
            $city,
            $region,
            $postalCode,
            $countryCode,
            $line1Norm,
            $cityNorm,
            $regionNorm,
            $postalNorm,
            $this->floatOverride($overrides, 'latitude'),
            $this->floatOverride($overrides, 'longitude'),
            $this->stringOverride($overrides, 'geohash'),
            AddressRecordPolicy::normalizeValidationStatus($this->stringOverride($overrides, 'validationStatus'), 'pending'),
            $this->stringOverride($overrides, 'validationProvider'),
            $this->stringOverride($overrides, 'validatedAt'),
            '' !== $dedupeKey ? $dedupeKey : null,
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
                'postalCode' => $postalCode,
                'countryCode' => $countryCode,
            ],
            isset($overrides['normalizedSnapshot']) && is_array($overrides['normalizedSnapshot']) ? $overrides['normalizedSnapshot'] : [
                'line1Norm' => $line1Norm,
                'cityNorm' => $cityNorm,
                'regionNorm' => $regionNorm,
                'postalCodeNorm' => $postalNorm,
            ],
            $this->stringOverride($overrides, 'providerDigest') ?? 'sha256:'.hash('sha256', $line1.'|'.$city.'|'.$countryCode),
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

    /** @param array<string, mixed> $overrides */
    private function stringOverride(array $overrides, string $key): ?string
    {
        return isset($overrides[$key]) && is_string($overrides[$key]) ? $overrides[$key] : null;
    }

    /** @param array<string, mixed> $overrides */
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

    /** @param array<string, mixed> $overrides */
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

    /** @param array<string, mixed> $overrides */
    private function boolOverride(array $overrides, string $key): ?bool
    {
        return isset($overrides[$key]) && is_bool($overrides[$key]) ? $overrides[$key] : null;
    }

    private function nullableTrimmed(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
