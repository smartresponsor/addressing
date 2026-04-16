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
        $flags = $this->reviewFlags($address, $expectedNormalizationVersion);
        $hasEvidence = true === $flags['hasEvidence'];
        $isEvidenceMissing = true === $flags['isEvidenceMissing'];
        $isValidationUncertain = true === $flags['isValidationUncertain'];
        $isGovernanceConflict = true === $flags['isGovernanceConflict'];
        $isNormalizationStale = true === $flags['isNormalizationStale'];
        $isRevalidationDue = true === $flags['isRevalidationDue'];
        $reviewReason = $this->reviewReason([
            'isGovernanceConflict' => $isGovernanceConflict,
            'isValidationUncertain' => $isValidationUncertain,
            'isEvidenceMissing' => $isEvidenceMissing,
            'isRevalidationDue' => $isRevalidationDue,
            'isNormalizationStale' => $isNormalizationStale,
            'governanceStatus' => $address->governanceStatus(),
        ]);

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

    /**
     * @return array{
     *   hasEvidence: bool,
     *   isEvidenceMissing: bool,
     *   isValidationUncertain: bool,
     *   isGovernanceConflict: bool,
     *   isNormalizationStale: bool,
     *   isRevalidationDue: bool
     * }
     */
    private function reviewFlags(AddressInterface $address, ?string $expectedNormalizationVersion): array
    {
        $hasEvidence = null !== $address->providerDigest()
            || null !== $address->rawInputSnapshot()
            || null !== $address->normalizedSnapshot();

        return [
            'hasEvidence' => $hasEvidence,
            'isEvidenceMissing' => !$hasEvidence,
            'isValidationUncertain' => 'uncertain' === $address->validationStatus() || 'uncertain' === $address->lastValidationStatus(),
            'isGovernanceConflict' => 'conflict' === $address->governanceStatus(),
            'isNormalizationStale' => null !== $expectedNormalizationVersion
                && $address->normalizationVersion() !== $expectedNormalizationVersion,
            'isRevalidationDue' => $this->isRevalidationDue($address->revalidationDueAt()),
        ];
    }

    private function isRevalidationDue(?string $revalidationDueAt): bool
    {
        if (null === $revalidationDueAt) {
            return false;
        }

        $timestamp = strtotime($revalidationDueAt);

        return false !== $timestamp && $timestamp <= time();
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

    /**
     * @param array{
     *   isGovernanceConflict: bool,
     *   isValidationUncertain: bool,
     *   isEvidenceMissing: bool,
     *   isRevalidationDue: bool,
     *   isNormalizationStale: bool,
     *   governanceStatus: string
     * } $flags
     */
    private function reviewReason(array $flags): ?string
    {
        if ($flags['isGovernanceConflict']) {
            return 'governanceConflict';
        }
        if ('duplicate' === $flags['governanceStatus']) {
            return 'duplicateReview';
        }
        if ($flags['isValidationUncertain']) {
            return 'uncertainValidation';
        }
        if ($flags['isEvidenceMissing']) {
            return 'evidenceMissing';
        }
        if ($flags['isRevalidationDue']) {
            return 'dueForRevalidation';
        }
        if ($flags['isNormalizationStale']) {
            return 'staleNormalizationVersion';
        }

        return null;
    }

    private function primaryGovernanceLinkId(AddressInterface $address): ?string
    {
        return array_find([$address->duplicateOfId(), $address->supersededById(), $address->aliasOfId(), $address->conflictWithId()], fn ($candidate) => null !== $candidate && '' !== $candidate);
    }
}
