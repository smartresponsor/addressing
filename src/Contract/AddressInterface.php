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
 *
 * @psalm-mutable
 */
interface AddressInterface
{
    /**
     * Exposes the validation state grouped behind its dedicated contract boundary.
     *
     * @psalm-mutation-free
     */
    public function validationState(): AddressValidationStateInterface;

    /**
     * Exposes governance relationships and status through the dedicated governance contract.
     *
     * @psalm-mutation-free
     */
    public function governanceState(): AddressGovernanceStateInterface;

    /**
     * Exposes revalidation scheduling and history through the dedicated state contract.
     *
     * @psalm-mutation-free
     */
    public function revalidationState(): AddressRevalidationStateInterface;

    /**
     * Returns the stable identifier of this persisted address record.
     *
     * @psalm-mutation-free
     */
    public function id(): string;

    /**
     * Returns the optional business owner identifier associated with this address.
     *
     * @psalm-mutation-free
     */
    public function ownerId(): ?string;

    /**
     * Returns the optional canonical vendor identifier associated with this address.
     *
     * @psalm-mutation-free
     */
    public function vendorId(): ?string;

    /**
     * Returns the primary human-entered street address line.
     *
     * @psalm-mutation-free
     */
    public function line1(): string;

    /**
     * Returns the optional secondary street or unit address line.
     *
     * @psalm-mutation-free
     */
    public function line2(): ?string;

    /**
     * Returns the locality or city recorded for the address.
     *
     * @psalm-mutation-free
     */
    public function city(): string;

    /**
     * Returns the optional administrative region recorded for the address.
     *
     * @psalm-mutation-free
     */
    public function region(): ?string;

    /**
     * Returns the optional postal code recorded for the address.
     *
     * @psalm-mutation-free
     */
    public function postalCode(): ?string;

    /**
     * Returns the country code governing address interpretation and validation.
     *
     * @psalm-mutation-free
     */
    public function countryCode(): string;

    /**
     * Returns the normalized primary address line when normalization is available.
     *
     * @psalm-mutation-free
     */
    public function line1Norm(): ?string;

    /**
     * Returns the normalized locality value when normalization is available.
     *
     * @psalm-mutation-free
     */
    public function cityNorm(): ?string;

    /**
     * Returns the normalized administrative region when normalization is available.
     *
     * @psalm-mutation-free
     */
    public function regionNorm(): ?string;

    /**
     * Returns the normalized postal code when normalization is available.
     *
     * @psalm-mutation-free
     */
    public function postalCodeNorm(): ?string;

    /**
     * Returns the validated latitude when geospatial evidence is available.
     *
     * @psalm-mutation-free
     */
    public function latitude(): ?float;

    /**
     * Returns the validated longitude when geospatial evidence is available.
     *
     * @psalm-mutation-free
     */
    public function longitude(): ?float;

    /**
     * Returns the derived geohash when geospatial evidence is available.
     *
     * @psalm-mutation-free
     */
    public function geohash(): ?string;

    /**
     * Returns the current canonical validation status for this address.
     *
     * @psalm-mutation-free
     */
    public function validationStatus(): string;

    /**
     * Returns the provider that produced the current validation evidence.
     *
     * @psalm-mutation-free
     */
    public function validationProvider(): ?string;

    /**
     * Returns when the current validation evidence was produced.
     *
     * @psalm-mutation-free
     */
    public function validatedAt(): ?string;

    /**
     * Returns the deduplication key used to correlate equivalent address records.
     *
     * @psalm-mutation-free
     */
    public function dedupeKey(): ?string;

    /**
     * Returns the fingerprint representing the current validation evidence state.
     *
     * @psalm-mutation-free
     */
    public function validationFingerprint(): ?string;

    /**
     * Returns the provider-native validation payload retained as audit evidence.
     *
     * @return array<string, mixed>|null
     *
     * @psalm-mutation-free
     */
    public function validationRaw(): ?array;

    /**
     * Returns the normalized provider verdict retained for downstream decisions.
     *
     * @return array<array-key, mixed>|null
     *
     * @psalm-mutation-free
     */
    public function validationVerdict(): ?array;

    /**
     * Returns the provider assessment of postal deliverability when available.
     *
     * @psalm-mutation-free
     */
    public function validationDeliverable(): ?bool;

    /**
     * Returns the provider-reported precision level of the validation result.
     *
     * @psalm-mutation-free
     */
    public function validationGranularity(): ?string;

    /**
     * Returns the normalized validation quality score when one is available.
     *
     * @psalm-mutation-free
     */
    public function validationQuality(): ?int;

    /**
     * Returns the upstream system from which this address originated.
     *
     * @psalm-mutation-free
     */
    public function sourceSystem(): ?string;

    /**
     * Returns the source classification used to interpret upstream provenance.
     *
     * @psalm-mutation-free
     */
    public function sourceType(): ?string;

    /**
     * Returns the upstream source reference used for correlation and traceability.
     *
     * @psalm-mutation-free
     */
    public function sourceReference(): ?string;

    /**
     * Returns the normalization ruleset version applied to this address.
     *
     * @psalm-mutation-free
     */
    public function normalizationVersion(): ?string;

    /**
     * Returns the retained pre-normalization input snapshot for audit comparison.
     *
     * @return array<string, mixed>|null
     *
     * @psalm-mutation-free
     */
    public function rawInputSnapshot(): ?array;

    /**
     * Returns the retained normalized address snapshot for audit comparison.
     *
     * @return array<string, mixed>|null
     *
     * @psalm-mutation-free
     */
    public function normalizedSnapshot(): ?array;

    /**
     * Returns the digest of provider evidence used for change detection.
     *
     * @psalm-mutation-free
     */
    public function providerDigest(): ?string;

    /**
     * Returns the current governance classification assigned to this address.
     *
     * @psalm-mutation-free
     */
    public function governanceStatus(): string;

    /**
     * Returns the canonical record identifier when this address is a duplicate.
     *
     * @psalm-mutation-free
     */
    public function duplicateOfId(): ?string;

    /**
     * Returns the replacement record identifier when this address is superseded.
     *
     * @psalm-mutation-free
     */
    public function supersededById(): ?string;

    /**
     * Returns the canonical record identifier when this address is an alias.
     *
     * @psalm-mutation-free
     */
    public function aliasOfId(): ?string;

    /**
     * Returns the related record identifier when governance detected a conflict.
     *
     * @psalm-mutation-free
     */
    public function conflictWithId(): ?string;

    /**
     * Returns when policy next requires this address to be revalidated.
     *
     * @psalm-mutation-free
     */
    public function revalidationDueAt(): ?string;

    /**
     * Returns the policy identifier governing this address revalidation schedule.
     *
     * @psalm-mutation-free
     */
    public function revalidationPolicy(): ?string;

    /**
     * Returns the provider used during the most recent validation attempt.
     *
     * @psalm-mutation-free
     */
    public function lastValidationProvider(): ?string;

    /**
     * Returns the outcome recorded for the most recent validation attempt.
     *
     * @psalm-mutation-free
     */
    public function lastValidationStatus(): ?string;

    /**
     * Returns the score recorded for the most recent validation attempt.
     *
     * @psalm-mutation-free
     */
    public function lastValidationScore(): ?int;

    /**
     * Returns when this address record was originally created.
     *
     * @psalm-mutation-free
     */
    public function createdAt(): string;

    /**
     * Returns when this address record was last materially updated.
     *
     * @psalm-mutation-free
     */
    public function updatedAt(): ?string;

    /**
     * Returns when this address record was soft-deleted, if applicable.
     *
     * @psalm-mutation-free
     */
    public function deletedAt(): ?string;
}
