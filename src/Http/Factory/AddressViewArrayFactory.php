<?php

declare(strict_types=1);

namespace App\Http\Factory;

use App\EntityInterface\Record\AddressInterface;

final readonly class AddressViewArrayFactory
{
    /** @return array<string, mixed> */
    public function toArray(AddressInterface $address, ?string $expectedNormalizationVersion): array
    {
        $governanceLinkId = $this->primaryGovernanceLinkId($address);
        $hasEvidence = null !== $address->providerDigest()
            || null !== $address->rawInputSnapshot()
            || null !== $address->normalizedSnapshot();
        $isRevalidationDue = null !== $address->revalidationDueAt()
            && false !== strtotime($address->revalidationDueAt())
            && strtotime($address->revalidationDueAt()) <= time();
        $isEvidenceMissing = !$hasEvidence;
        $isValidationUncertain = 'uncertain' === $address->validationStatus() || 'uncertain' === $address->lastValidationStatus();
        $isGovernanceConflict = 'conflict' === $address->governanceStatus();
        $isNormalizationStale = null !== $expectedNormalizationVersion
            && $address->normalizationVersion() !== $expectedNormalizationVersion;
        $reviewReason = $this->reviewReason($isGovernanceConflict, $isValidationUncertain, $isEvidenceMissing, $isRevalidationDue, $isNormalizationStale, $address->governanceStatus());

        return [
            'id' => $address->id(),
            'ownerId' => $address->ownerId(),
            'vendorId' => $address->vendorId(),
            'line1' => $address->line1(),
            'line2' => $address->line2(),
            'city' => $address->city(),
            'region' => $address->region(),
            'postalCode' => $address->postalCode(),
            'countryCode' => $address->countryCode(),
            'line1Norm' => $address->line1Norm(),
            'cityNorm' => $address->cityNorm(),
            'regionNorm' => $address->regionNorm(),
            'postalCodeNorm' => $address->postalCodeNorm(),
            'latitude' => $address->latitude(),
            'longitude' => $address->longitude(),
            'geohash' => $address->geohash(),
            'validationStatus' => $address->validationStatus(),
            'validationProvider' => $address->validationProvider(),
            'validatedAt' => $address->validatedAt(),
            'dedupeKey' => $address->dedupeKey(),
            'sourceSystem' => $address->sourceSystem(),
            'sourceType' => $address->sourceType(),
            'sourceReference' => $address->sourceReference(),
            'normalizationVersion' => $address->normalizationVersion(),
            'rawInputSnapshot' => $address->rawInputSnapshot(),
            'normalizedSnapshot' => $address->normalizedSnapshot(),
            'providerDigest' => $address->providerDigest(),
            'hasEvidence' => $hasEvidence,
            'isEvidenceMissing' => $isEvidenceMissing,
            'isValidationUncertain' => $isValidationUncertain,
            'isGovernanceConflict' => $isGovernanceConflict,
            'isNormalizationStale' => $isNormalizationStale,
            'requiresReview' => null !== $reviewReason,
            'reviewReason' => $reviewReason,
            'governanceStatus' => $address->governanceStatus(),
            'governanceLinkId' => $governanceLinkId,
            'hasGovernanceLink' => null !== $governanceLinkId,
            'duplicateOfId' => $address->duplicateOfId(),
            'supersededById' => $address->supersededById(),
            'aliasOfId' => $address->aliasOfId(),
            'conflictWithId' => $address->conflictWithId(),
            'revalidationDueAt' => $address->revalidationDueAt(),
            'isRevalidationDue' => $isRevalidationDue,
            'revalidationPolicy' => $address->revalidationPolicy(),
            'lastValidationProvider' => $address->lastValidationProvider(),
            'lastValidationStatus' => $address->lastValidationStatus(),
            'lastValidationScore' => $address->lastValidationScore(),
            'createdAt' => $address->createdAt(),
            'updatedAt' => $address->updatedAt(),
            'deletedAt' => $address->deletedAt(),
        ];
    }

    /** @return array{id: string, line1: string, city: string, countryCode: string, governanceStatus: string, validationStatus: string} */
    public function previewRow(AddressInterface $address): array
    {
        return [
            'id' => $address->id(),
            'line1' => $address->line1(),
            'city' => $address->city(),
            'countryCode' => $address->countryCode(),
            'governanceStatus' => $address->governanceStatus(),
            'validationStatus' => $address->validationStatus(),
        ];
    }

    private function reviewReason(
        bool $isGovernanceConflict,
        bool $isValidationUncertain,
        bool $isEvidenceMissing,
        bool $isRevalidationDue,
        bool $isNormalizationStale,
        string $governanceStatus,
    ): ?string {
        if ($isGovernanceConflict) {
            return 'governanceConflict';
        }
        if ('duplicate' === $governanceStatus) {
            return 'duplicateReview';
        }
        if ($isValidationUncertain) {
            return 'uncertainValidation';
        }
        if ($isEvidenceMissing) {
            return 'evidenceMissing';
        }
        if ($isRevalidationDue) {
            return 'dueForRevalidation';
        }
        if ($isNormalizationStale) {
            return 'staleNormalizationVersion';
        }

        return null;
    }

    private function primaryGovernanceLinkId(AddressInterface $address): ?string
    {
        foreach ([$address->duplicateOfId(), $address->supersededById(), $address->aliasOfId(), $address->conflictWithId()] as $candidate) {
            if (null !== $candidate && '' !== $candidate) {
                return $candidate;
            }
        }

        return null;
    }
}
