<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Factory;

use App\Addressing\DTO\AddressManageDTO;
use App\Addressing\Policy\AddressRecordPolicy;
use App\Addressing\Value\AddressCountryCode;
use App\Addressing\Value\AddressPostalCode;
use App\Addressing\Value\AddressStreetLine;
use App\Addressing\Value\AddressSubdivision;
use App\Addressing\Value\Record\AddressRecord;
use Symfony\Component\Uid\Ulid;

final class AddressInputFactory
{
    /**
     * @param array<string, mixed> $overrides
     */
    public function fromManageDto(AddressManageDTO $addressManageDto, array $overrides = []): AddressRecord
    {
        $line1 = (string) new AddressStreetLine($addressManageDto->line1);
        $countryCode = (string) new AddressCountryCode($addressManageDto->countryCode);
        $postalCode = $this->postalCode($addressManageDto);
        $region = $this->region($addressManageDto);
        $city = trim($addressManageDto->city);
        $ownerId = $this->nullableTrimmed($addressManageDto->ownerId);
        $vendorId = $this->nullableTrimmed($addressManageDto->vendorId);
        $line2 = $this->nullableTrimmed($addressManageDto->line2);
        $normalized = $this->normalizedAddress($line1, $city, $region, $postalCode);

        return $this->recordFromInput($overrides, $line1, $line2, $city, $region, $postalCode, $countryCode, $ownerId, $vendorId, $normalized);
    }

    /**
     * @param array<string, mixed> $overrides
     * @param array{line1Norm: string, cityNorm: string, regionNorm: ?string, postalCodeNorm: ?string} $normalized
     */
    private function recordFromInput(array $overrides, string $line1, ?string $line2, string $city, ?string $region, ?string $postalCode, string $countryCode, ?string $ownerId, ?string $vendorId, array $normalized): AddressRecord
    {
        $createdAt = new \DateTimeImmutable();
        $now = $this->stringOverride($overrides, 'createdAt') ?? $createdAt->format('Y-m-d H:i:sP');
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

        $addressRecord = new AddressRecord(
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
        );
        $this->applyOptionalOverrides($addressRecord, $overrides, $validationDeliverable, $rawInputSnapshot, $normalized, $line1, $city, $countryCode);

        return $addressRecord;
    }

    /**
     * @param array<string, mixed> $overrides
     * @param array<string, mixed> $rawInputSnapshot
     * @param array{line1Norm: string, cityNorm: string, regionNorm: ?string, postalCodeNorm: ?string} $normalized
     */
    private function applyOptionalOverrides(AddressRecord $record, array $overrides, ?bool $validationDeliverable, array $rawInputSnapshot, array $normalized, string $line1, string $city, string $countryCode): void
    {
        $record->validationFingerprint = $this->stringOverride($overrides, 'validationFingerprint');
        $record->validationRaw = isset($overrides['validationRaw']) && is_array($overrides['validationRaw']) ? $overrides['validationRaw'] : null;
        $record->validationVerdict = isset($overrides['validationVerdict']) && is_array($overrides['validationVerdict']) ? $overrides['validationVerdict'] : null;
        $record->validationDeliverable = $validationDeliverable;
        $record->validationGranularity = $this->stringOverride($overrides, 'validationGranularity');
        $record->validationQuality = $this->intOverride($overrides, 'validationQuality');
        $record->sourceSystem = $this->stringOverride($overrides, 'sourceSystem') ?? 'symfony-demo';
        $record->sourceType = AddressRecordPolicy::normalizeSourceType($this->stringOverride($overrides, 'sourceType') ?? 'manual');
        $record->sourceReference = $this->stringOverride($overrides, 'sourceReference');
        $record->normalizationVersion = $this->stringOverride($overrides, 'normalizationVersion') ?? 'demo-v1';
        $record->rawInputSnapshot = $rawInputSnapshot;
        $record->normalizedSnapshot = isset($overrides['normalizedSnapshot']) && is_array($overrides['normalizedSnapshot']) ? $overrides['normalizedSnapshot'] : $normalized;
        $record->providerDigest = $this->stringOverride($overrides, 'providerDigest') ?? 'sha256:'.hash('sha256', $line1.'|'.$city.'|'.$countryCode);
        $record->governanceStatus = AddressRecordPolicy::normalizeGovernanceStatus($this->stringOverride($overrides, 'governanceStatus') ?? 'canonical');
        $record->duplicateOfId = $this->stringOverride($overrides, 'duplicateOfId');
        $record->supersededById = $this->stringOverride($overrides, 'supersededById');
        $record->aliasOfId = $this->stringOverride($overrides, 'aliasOfId');
        $record->conflictWithId = $this->stringOverride($overrides, 'conflictWithId');
        $record->revalidationDueAt = $this->stringOverride($overrides, 'revalidationDueAt');
        $record->revalidationPolicy = AddressRecordPolicy::normalizeRevalidationPolicy($this->stringOverride($overrides, 'revalidationPolicy') ?? 'quarterly');
        $record->lastValidationProvider = $this->stringOverride($overrides, 'lastValidationProvider');
        $record->lastValidationStatus = AddressRecordPolicy::normalizeLastValidationStatus($this->stringOverride($overrides, 'lastValidationStatus'));
        $record->lastValidationScore = $this->intOverride($overrides, 'lastValidationScore');
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

    private function postalCode(AddressManageDTO $addressManageDto): ?string
    {
        return null !== $addressManageDto->postalCode && '' !== trim($addressManageDto->postalCode)
            ? (string) new AddressPostalCode($addressManageDto->postalCode)
            : null;
    }

    private function region(AddressManageDTO $addressManageDto): ?string
    {
        return null !== $addressManageDto->region && '' !== trim($addressManageDto->region)
            ? (string) new AddressSubdivision($addressManageDto->region)
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
