<?php

declare(strict_types=1);

namespace App\Addressing\Factory;

use App\Addressing\Contract\AddressInterface;

/**
 * Projects Addressing records into stable transport arrays enriched with review and governance signals.
 */
final readonly class AddressViewArrayFactory
{
    /**
     * Builds the full address view payload and derives review state from validation, evidence, and governance metadata.
     *
     * @return array<string, mixed>
     */
    public function toArray(AddressInterface $address, ?string $expectedNormalizationVersion): array
    {
        $flags = $this->reviewFlags($address, $expectedNormalizationVersion);
        $reviewReason = $this->reviewReason([
            'isGovernanceConflict' => $flags['isGovernanceConflict'],
            'isValidationUncertain' => $flags['isValidationUncertain'],
            'isEvidenceMissing' => $flags['isEvidenceMissing'],
            'isRevalidationDue' => $flags['isRevalidationDue'],
            'isNormalizationStale' => $flags['isNormalizationStale'],
            'governanceStatus' => $address->governanceStatus(),
        ]);

        return array_merge(
            $this->addressPayload($address),
            $this->reviewPayload($address, $flags, $reviewReason),
        );
    }

    /** @return array<string, mixed> */
    private function addressPayload(AddressInterface $address): array
    {
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
        ];
    }

    /**
     * @param array{
     *   hasEvidence: bool,
     *   isEvidenceMissing: bool,
     *   isValidationUncertain: bool,
     *   isGovernanceConflict: bool,
     *   isNormalizationStale: bool,
     *   isRevalidationDue: bool
     * } $flags
     * @return array<string, mixed>
     */
    private function reviewPayload(AddressInterface $address, array $flags, ?string $reviewReason): array
    {
        $governanceLinkId = $this->primaryGovernanceLinkId($address);

        return [
            'hasEvidence' => $flags['hasEvidence'],
            'isEvidenceMissing' => $flags['isEvidenceMissing'],
            'isValidationUncertain' => $flags['isValidationUncertain'],
            'isGovernanceConflict' => $flags['isGovernanceConflict'],
            'isNormalizationStale' => $flags['isNormalizationStale'],
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
            'isRevalidationDue' => $flags['isRevalidationDue'],
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

    /**
     * Builds the compact address projection used by preview and portfolio-oriented consumers.
     *
     * @return array{id: string, line1: string, city: string, countryCode: string, governanceStatus: string, validationStatus: string}
     */
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
        return array_find([$address->duplicateOfId(), $address->supersededById(), $address->aliasOfId(), $address->conflictWithId()], fn ($candidate): bool => null !== $candidate && '' !== $candidate);
    }
}
