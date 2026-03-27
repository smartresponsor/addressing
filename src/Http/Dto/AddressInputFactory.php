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
        $now = $overrides['createdAt'] ?? (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');
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
            (string) ($overrides['id'] ?? new Ulid()),
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
            isset($overrides['latitude']) ? (float) $overrides['latitude'] : null,
            isset($overrides['longitude']) ? (float) $overrides['longitude'] : null,
            isset($overrides['geohash']) && is_string($overrides['geohash']) ? $overrides['geohash'] : null,
            AddressRecordPolicy::normalizeValidationStatus(isset($overrides['validationStatus']) && is_string($overrides['validationStatus']) ? $overrides['validationStatus'] : null, 'pending'),
            isset($overrides['validationProvider']) && is_string($overrides['validationProvider']) ? $overrides['validationProvider'] : null,
            isset($overrides['validatedAt']) && is_string($overrides['validatedAt']) ? $overrides['validatedAt'] : null,
            '' !== $dedupeKey ? $dedupeKey : null,
            (string) $now,
            isset($overrides['updatedAt']) && is_string($overrides['updatedAt']) ? $overrides['updatedAt'] : null,
            isset($overrides['deletedAt']) && is_string($overrides['deletedAt']) ? $overrides['deletedAt'] : null,
            isset($overrides['validationFingerprint']) && is_string($overrides['validationFingerprint']) ? $overrides['validationFingerprint'] : null,
            isset($overrides['validationRaw']) && is_array($overrides['validationRaw']) ? $overrides['validationRaw'] : null,
            isset($overrides['validationVerdict']) && is_array($overrides['validationVerdict']) ? $overrides['validationVerdict'] : null,
            isset($overrides['validationDeliverable']) ? (bool) $overrides['validationDeliverable'] : null,
            isset($overrides['validationGranularity']) && is_string($overrides['validationGranularity']) ? $overrides['validationGranularity'] : null,
            isset($overrides['validationQuality']) ? (int) $overrides['validationQuality'] : null,
            isset($overrides['sourceSystem']) && is_string($overrides['sourceSystem']) ? $overrides['sourceSystem'] : 'symfony-demo',
            AddressRecordPolicy::normalizeSourceType(isset($overrides['sourceType']) && is_string($overrides['sourceType']) ? $overrides['sourceType'] : 'manual'),
            isset($overrides['sourceReference']) && is_string($overrides['sourceReference']) ? $overrides['sourceReference'] : null,
            isset($overrides['normalizationVersion']) && is_string($overrides['normalizationVersion']) ? $overrides['normalizationVersion'] : 'demo-v1',
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
            isset($overrides['providerDigest']) && is_string($overrides['providerDigest']) ? $overrides['providerDigest'] : 'sha256:'.hash('sha256', $line1.'|'.$city.'|'.$countryCode),
            AddressRecordPolicy::normalizeGovernanceStatus(isset($overrides['governanceStatus']) && is_string($overrides['governanceStatus']) ? $overrides['governanceStatus'] : 'canonical'),
            isset($overrides['duplicateOfId']) && is_string($overrides['duplicateOfId']) ? $overrides['duplicateOfId'] : null,
            isset($overrides['supersededById']) && is_string($overrides['supersededById']) ? $overrides['supersededById'] : null,
            isset($overrides['aliasOfId']) && is_string($overrides['aliasOfId']) ? $overrides['aliasOfId'] : null,
            isset($overrides['conflictWithId']) && is_string($overrides['conflictWithId']) ? $overrides['conflictWithId'] : null,
            isset($overrides['revalidationDueAt']) && is_string($overrides['revalidationDueAt']) ? $overrides['revalidationDueAt'] : null,
            AddressRecordPolicy::normalizeRevalidationPolicy(isset($overrides['revalidationPolicy']) && is_string($overrides['revalidationPolicy']) ? $overrides['revalidationPolicy'] : 'quarterly'),
            isset($overrides['lastValidationProvider']) && is_string($overrides['lastValidationProvider']) ? $overrides['lastValidationProvider'] : null,
            AddressRecordPolicy::normalizeLastValidationStatus(isset($overrides['lastValidationStatus']) && is_string($overrides['lastValidationStatus']) ? $overrides['lastValidationStatus'] : null),
            isset($overrides['lastValidationScore']) ? (int) $overrides['lastValidationScore'] : null,
        );
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
