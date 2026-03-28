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
    public function fromManageDto(AddressManageDto $dto, array $overrides = []): AddressData
    {
        $now = self::stringOverride($overrides, 'createdAt') ?? (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');
        $line1 = (string) new StreetLine($dto->line1);
        $countryCode = (string) new CountryCode($dto->countryCode);
        $postalCode = null;
        if (null !== $dto->postalCode && '' !== trim($dto->postalCode)) {
            $postalCode = (string) new PostalCode($dto->postalCode);
        }

        $region = null;
        if (null !== $dto->region && '' !== trim($dto->region)) {
            $region = (string) new Subdivision($dto->region);
        }

        $city = trim($dto->city);
        $ownerId = self::nullableTrimmed($dto->ownerId);
        $vendorId = self::nullableTrimmed($dto->vendorId);
        $line2 = self::nullableTrimmed($dto->line2);

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
            self::stringOverride($overrides, 'id') ?? (string) new Ulid(),
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
            self::floatOverride($overrides, 'latitude'),
            self::floatOverride($overrides, 'longitude'),
            self::stringOverride($overrides, 'geohash'),
            AddressRecordPolicy::normalizeValidationStatus(self::stringOverride($overrides, 'validationStatus'), 'pending'),
            self::stringOverride($overrides, 'validationProvider'),
            self::stringOverride($overrides, 'validatedAt'),
            '' !== $dedupeKey ? $dedupeKey : null,
            $now,
            self::stringOverride($overrides, 'updatedAt'),
            self::stringOverride($overrides, 'deletedAt'),
            self::stringOverride($overrides, 'validationFingerprint'),
            isset($overrides['validationRaw']) && is_array($overrides['validationRaw']) ? $overrides['validationRaw'] : null,
            isset($overrides['validationVerdict']) && is_array($overrides['validationVerdict']) ? $overrides['validationVerdict'] : null,
            self::boolOverride($overrides, 'validationDeliverable'),
            self::stringOverride($overrides, 'validationGranularity'),
            self::intOverride($overrides, 'validationQuality'),
            self::stringOverride($overrides, 'sourceSystem') ?? 'symfony-demo',
            AddressRecordPolicy::normalizeSourceType(self::stringOverride($overrides, 'sourceType') ?? 'manual'),
            self::stringOverride($overrides, 'sourceReference'),
            self::stringOverride($overrides, 'normalizationVersion') ?? 'demo-v1',
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
            self::stringOverride($overrides, 'providerDigest') ?? 'sha256:'.hash('sha256', $line1.'|'.$city.'|'.$countryCode),
            AddressRecordPolicy::normalizeGovernanceStatus(self::stringOverride($overrides, 'governanceStatus') ?? 'canonical'),
            self::stringOverride($overrides, 'duplicateOfId'),
            self::stringOverride($overrides, 'supersededById'),
            self::stringOverride($overrides, 'aliasOfId'),
            self::stringOverride($overrides, 'conflictWithId'),
            self::stringOverride($overrides, 'revalidationDueAt'),
            AddressRecordPolicy::normalizeRevalidationPolicy(self::stringOverride($overrides, 'revalidationPolicy') ?? 'quarterly'),
            self::stringOverride($overrides, 'lastValidationProvider'),
            AddressRecordPolicy::normalizeLastValidationStatus(self::stringOverride($overrides, 'lastValidationStatus')),
            self::intOverride($overrides, 'lastValidationScore'),
        );
    }

    /** @param array<string, mixed> $overrides */
    private static function stringOverride(array $overrides, string $key): ?string
    {
        return isset($overrides[$key]) && is_string($overrides[$key]) ? $overrides[$key] : null;
    }

    /** @param array<string, mixed> $overrides */
    private static function intOverride(array $overrides, string $key): ?int
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
    private static function floatOverride(array $overrides, string $key): ?float
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
    private static function boolOverride(array $overrides, string $key): ?bool
    {
        return isset($overrides[$key]) && is_bool($overrides[$key]) ? $overrides[$key] : null;
    }

    private static function nullableTrimmed(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
