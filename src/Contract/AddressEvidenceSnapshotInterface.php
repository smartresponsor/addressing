<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 */
declare(strict_types=1);

namespace App\Addressing\Contract;

/**
 * Defines the immutable evidence snapshot contract retained for validated Addressing records.
 *
 * Implementations expose validation provenance, normalized/raw snapshots, governance identity,
 * and provider evidence without leaking persistence or transport-specific representations.
 */
interface AddressEvidenceSnapshotInterface
{
    public function id(): string;

    public function addressId(): string;

    public function ownerId(): ?string;

    public function vendorId(): ?string;

    public function sourceSystem(): ?string;

    public function sourceType(): ?string;

    public function sourceReference(): ?string;

    public function validatedBy(): ?string;

    public function validatedAt(): ?string;

    public function normalizationVersion(): ?string;

    /**
     * Return the original address input captured before normalization and validation processing.
     *
     * @return array<string, mixed>|null
     */
    public function rawInputSnapshot(): ?array;

    /**
     * Return the normalized address snapshot retained as evidence for this validation event.
     *
     * @return array<string, mixed>|null
     */
    public function normalizedSnapshot(): ?array;

    /**
     * Return the final validation status recorded for the immutable evidence snapshot.
     */
    public function validationStatus(): string;

    /**
     * Return the optional validation confidence score captured with the evidence snapshot.
     */
    public function validationScore(): ?int;

    /**
     * Return structured validation issues reported while producing this evidence snapshot.
     *
     * @return array<string, mixed>|null
     */
    public function validationIssues(): ?array;

    /**
     * Return the optional digest that identifies the provider evidence behind this snapshot.
     */
    public function providerDigest(): ?string;

    /**
     * Return the immutable snapshot creation timestamp in its serialized contract form.
     */
    public function createdAt(): string;
}
