<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Contract;

/**
 * Defines the validation, provenance, normalization, and provider-evidence state of an address.
 *
 * This boundary exposes Addressing-owned evidence as scalar and structured values while keeping
 * provider execution, persistence details, and transport-specific representations outside callers.
 */
interface AddressValidationStateInterface
{
    /** Return the current validation status recorded by Addressing. */
    public function validationStatus(): string;

    /** Return the provider identifier associated with the current validation evidence. */
    public function validationProvider(): ?string;

    /** Return when the current validation evidence was produced, when available. */
    public function validatedAt(): ?string;

    /** Return the stable fingerprint used to identify the current validation evidence. */
    public function validationFingerprint(): ?string;

    /**
     * Return the provider-native validation payload retained for diagnostics and evidence review.
     *
     * @return array<string, mixed>|null
     */
    public function validationRaw(): ?array;

    /**
     * Return the normalized validation verdict retained by the Addressing component.
     *
     * @return array<string, mixed>|null
     */
    public function validationVerdict(): ?array;

    /** Return whether the recorded evidence considers the address deliverable, when known. */
    public function validationDeliverable(): ?bool;

    /** Return the provider or component granularity assigned to the validated address. */
    public function validationGranularity(): ?string;

    /** Return the normalized validation quality score retained by Addressing, when available. */
    public function validationQuality(): ?int;

    /** Return the upstream system that supplied the address input or evidence. */
    public function sourceSystem(): ?string;

    /** Return the upstream source category used to classify the address provenance. */
    public function sourceType(): ?string;

    /** Return the source-specific reference used to correlate the address with upstream data. */
    public function sourceReference(): ?string;

    /** Return the normalization contract version used to produce the retained normalized state. */
    public function normalizationVersion(): ?string;

    /**
     * Return the original input snapshot captured before Addressing normalization and validation.
     *
     * @return array<string, mixed>|null
     */
    public function rawInputSnapshot(): ?array;

    /**
     * Return the normalized address snapshot retained with the current validation evidence.
     *
     * @return array<string, mixed>|null
     */
    public function normalizedSnapshot(): ?array;

    /** Return the digest identifying provider evidence associated with the current validation state. */
    public function providerDigest(): ?string;
}
