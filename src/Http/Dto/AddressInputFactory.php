<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Dto;

use App\Contract\Message\AddressRecordPolicy;
use App\Entity\Record\AddressRecord;
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
    public function fromManageDto(AddressManageDto $addressManageDto, array $overrides = []): AddressRecord
    {
        $createdAt = new \DateTimeImmutable();
        $now = $this->stringOverride($overrides, 'createdAt') ?? $createdAt->format('Y-m-d H:i:sP');
        $line1 = (string) new StreetLine($addressManageDto->line1);
        $countryCode = (string) new CountryCode($addressManageDto->countryCode);
        $postalCode = $this->postalCode($addressManageDto);
        $region = $this->region($addressManageDto);
        $city = trim($addressManageDto->city);
        $ownerId = $this->nullableTrimmed($addressManageDto->ownerId);
        $vendorId = $this->nullableTrimmed($addressManageDto->vendorId);
        $line2 = $this->nullableTrimmed($addressManageDto->line2);
        $normalized = $this->normalizedAddress($line1, $city, $region, $postalCode);
        $dedupeKey = $this->dedupeKey($normalized, $countryCode, $ownerId, $vendorId);
        $validationDeliverable = isset($overrides['validationDeliverable']) && is_bool($overrides['validationDeliverable'])
            ? $overrides['validationDeliverable']
            : null;
        $rawInputSnapshot = isset($overrides['rawInputSnapshot']) && is_array($overrides['rawInputSnapshot'])
            ? $overrides['rawInputSnapshot']
            : [
                'line1' => $line1,
                'line2' => $line2,
                'city' => $city,
                'region' => $region,
                'postalCode' => $postalCode,
                'countryCode' => $countryCode,
            ];

        return new AddressRecord(
            $this->stringOverride($overrides, 'id') ?? (string) new Ulid(),
            $ownerId,
            $vendorId,
            $line1,
            $line2,
            $city,
            $region,
            $postalCode,
            $countryCode,
            $normalized['line1Norm'],
            $normalized['cityNorm'],
            $normalized['regionNorm'],
            $normalized['postalCodeNorm'],
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
            $validationDeliverable,
            $this->stringOverride($overrides, 'validationGranularity'),
            $this->intOverride($overrides, 'validationQuality'),
            $this->stringOverride($overrides, 'sourceSystem') ?? 'symfony-demo',
            AddressRecordPolicy::normalizeSourceType($this->stringOverride($overrides, 'sourceType') ?? 'manual'),
            $this->stringOverride($overrides, 'sourceReference'),
            $this->stringOverride($overrides, 'normalizationVersion') ?? 'demo-v1',
            $rawInputSnapshot,
            isset($overrides['normalizedSnapshot']) && is_array($overrides['normalizedSnapshot'])
                ? $overrides['normalizedSnapshot']
                : $normalized,
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

    private function postalCode(AddressManageDto $addressManageDto): ?string
    {
        return null !== $addressManageDto->postalCode && '' !== trim($addressManageDto->postalCode)
            ? (string) new PostalCode($addressManageDto->postalCode)
            : null;
    }

    private function region(AddressManageDto $addressManageDto): ?string
    {
        return null !== $addressManageDto->region && '' !== trim($addressManageDto->region)
            ? (string) new Subdivision($addressManageDto->region)
            : null;
    }

    /**
     * @return array{
     *     line1Norm: string,
     *     cityNorm: string,
     *     regionNorm: ?string,
     *     postalCodeNorm: ?string
     * }
     */
    private function normalizedAddress(string $line1, string $city, ?string $region, ?string $postalCode): array
    {
        return [
            'line1Norm' => strtolower($line1),
            'cityNorm' => strtolower($city),
            'regionNorm' => null !== $region ? strtolower($region) : null,
            'postalCodeNorm' => null !== $postalCode ? strtolower(str_replace(' ', '', $postalCode)) : null,
        ];
    }

    /**
     * @param array{line1Norm: string, cityNorm: string, regionNorm: ?string, postalCodeNorm: ?string} $normalized
     */
    private function dedupeKey(array $normalized, string $countryCode, ?string $ownerId, ?string $vendorId): string
    {
        return implode('|', array_filter([
            $normalized['line1Norm'],
            $normalized['cityNorm'],
            $normalized['regionNorm'],
            $normalized['postalCodeNorm'],
            strtolower($countryCode),
            $ownerId,
            $vendorId,
        ], static fn (?string $value): bool => null !== $value && '' !== $value));
    }
}
