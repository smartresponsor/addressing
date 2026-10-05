<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Contract;

/**
 * Defines the complete read contract for one persisted address aggregate.
 *
 * Consumers use this boundary to inspect address identity, normalized and validated data,
 * provenance, governance relations, revalidation state, and lifecycle timestamps without
 * depending on the concrete Doctrine entity implementation.
 */
interface AddressInterface
{
    /**
     * Exposes the validation state grouped behind its dedicated contract boundary.
     */
    public function validationState(): AddressValidationStateInterface;

    /**
     * Exposes governance relationships and status through the dedicated governance contract.
     */
    public function governanceState(): AddressGovernanceStateInterface;

    /**
     * Exposes revalidation scheduling and history through the dedicated state contract.
     */
    public function revalidationState(): AddressRevalidationStateInterface;

    /**
     * Returns the stable identifier of this persisted address record.
     */
    public function id(): string;

    /**
     * Returns the optional business owner identifier associated with this address.
     */
    public function ownerId(): ?string;

    /**
     * Returns the optional canonical vendor identifier associated with this address.
     */
    public function vendorId(): ?string;

    /**
     * Returns the primary human-entered street address line.
     */
    public function line1(): string;

    /**
     * Returns the optional secondary street or unit address line.
     */
    public function line2(): ?string;

    /**
     * Returns the locality or city recorded for the address.
     */
    public function city(): string;

    /**
     * Returns the optional administrative region recorded for the address.
     */
    public function region(): ?string;

    /**
     * Returns the optional postal code recorded for the address.
     */
    public function postalCode(): ?string;

    /**
     * Returns the country code governing address interpretation and validation.
     */
    public function countryCode(): string;

    /**
     * Returns the normalized primary address line when normalization is available.
     */
    public function line1Norm(): ?string;

    /**
     * Returns the normalized locality value when normalization is available.
     */
    public function cityNorm(): ?string;

    /**
     * Returns the normalized administrative region when normalization is available.
     */
    public function regionNorm(): ?string;

    /**
     * Returns the normalized postal code when normalization is available.
     */
    public function postalCodeNorm(): ?string;

    /**
     * Returns the validated latitude when geospatial evidence is available.
     */
    public function latitude(): ?float;

    /**
     * Returns the validated longitude when geospatial evidence is available.
     */
    public function longitude(): ?float;

    /**
     * Returns the derived geohash when geospatial evidence is available.
     */
    public function geohash(): ?string;

    /**
     * Returns the current canonical validation status for this address.
     */
    public function validationStatus(): string;

    /**
     * Returns the provider that produced the current validation evidence.
     */
    public function validationProvider(): ?string;

    /**
     * Returns when the current validation evidence was produced.
     */
    public function validatedAt(): ?string;

    /**
     * Returns the deduplication key used to correlate equivalent address records.
     */
    public function dedupeKey(): ?string;

    /**
     * Returns the fingerprint representing the current validation evidence state.
     */
    public function validationFingerprint(): ?string;

    /**
     * Returns the provider-native validation payload retained as audit evidence.
     *
     * @return array<string, mixed>|null
     */
    public function validationRaw(): ?array;

    /**
     * Returns the normalized provider verdict retained for downstream decisions.
     *
     * @return array<string, mixed>|null
     */
    public function validationVerdict(): ?array;

    /**
     * Returns the provider assessment of postal deliverability when available.
     */
    public function validationDeliverable(): ?bool;

    /**
     * Returns the provider-reported precision level of the validation result.
     */
    public function validationGranularity(): ?string;

    /**
     * Returns the normalized validation quality score when one is available.
     */
    public function validationQuality(): ?int;

    /**
     * Returns the upstream system from which this address originated.
     */
    public function sourceSystem(): ?string;

    /**
     * Returns the source classification used to interpret upstream provenance.
     */
    public function sourceType(): ?string;

    /**
     * Returns the upstream source reference used for correlation and traceability.
     */
    public function sourceReference(): ?string;

    /**
     * Returns the normalization ruleset version applied to this address.
     */
    public function normalizationVersion(): ?string;

    /**
     * Returns the retained pre-normalization input snapshot for audit comparison.
     *
     * @return array<string, mixed>|null
     */
    public function rawInputSnapshot(): ?array;

    /**
     * Returns the retained normalized address snapshot for audit comparison.
     *
     * @return array<string, mixed>|null
     */
    public function normalizedSnapshot(): ?array;

    /**
     * Returns the digest of provider evidence used for change detection.
     */
    public function providerDigest(): ?string;

    /**
     * Returns the current governance classification assigned to this address.
     */
    public function governanceStatus(): string;

    /**
     * Returns the canonical record identifier when this address is a duplicate.
     */
    public function duplicateOfId(): ?string;

    /**
     * Returns the replacement record identifier when this address is superseded.
     */
    public function supersededById(): ?string;

    /**
     * Returns the canonical record identifier when this address is an alias.
     */
    public function aliasOfId(): ?string;

    /**
     * Returns the related record identifier when governance detected a conflict.
     */
    public function conflictWithId(): ?string;

    /**
     * Returns when policy next requires this address to be revalidated.
     */
    public function revalidationDueAt(): ?string;

    /**
     * Returns the policy identifier governing this address revalidation schedule.
     */
    public function revalidationPolicy(): ?string;

    /**
     * Returns the provider used during the most recent validation attempt.
     */
    public function lastValidationProvider(): ?string;

    /**
     * Returns the outcome recorded for the most recent validation attempt.
     */
    public function lastValidationStatus(): ?string;

    /**
     * Returns the score recorded for the most recent validation attempt.
     */
    public function lastValidationScore(): ?int;

    /**
     * Returns when this address record was originally created.
     */
    public function createdAt(): string;

    /**
     * Returns when this address record was last materially updated.
     */
    public function updatedAt(): ?string;

    /**
     * Returns when this address record was soft-deleted, if applicable.
     */
    public function deletedAt(): ?string;
}
