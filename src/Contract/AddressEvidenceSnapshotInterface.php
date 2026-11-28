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
 *
 * @psalm-mutable
 */
interface AddressEvidenceSnapshotInterface
{
    /** @psalm-mutation-free */
    public function id(): string;

    /** @psalm-mutation-free */
    public function addressId(): string;

    /** @psalm-mutation-free */
    public function ownerId(): ?string;

    /** @psalm-mutation-free */
    public function vendorId(): ?string;

    /** @psalm-mutation-free */
    public function sourceSystem(): ?string;

    /** @psalm-mutation-free */
    public function sourceType(): ?string;

    /** @psalm-mutation-free */
    public function sourceReference(): ?string;

    /** @psalm-mutation-free */
    public function validatedBy(): ?string;

    /** @psalm-mutation-free */
    public function validatedAt(): ?string;

    /** @psalm-mutation-free */
    public function normalizationVersion(): ?string;

    /**
     * Return the original address input captured before normalization and validation processing.
     *
     * @return array<string, mixed>|null
     *
     * @psalm-mutation-free
     */
    public function rawInputSnapshot(): ?array;

    /**
     * Return the normalized address snapshot retained as evidence for this validation event.
     *
     * @return array<string, mixed>|null
     *
     * @psalm-mutation-free
     */
    public function normalizedSnapshot(): ?array;

    /**
     * Return the final validation status recorded for the immutable evidence snapshot.
     *
     * @psalm-mutation-free
     */
    public function validationStatus(): string;

    /**
     * Return the optional validation confidence score captured with the evidence snapshot.
     *
     * @psalm-mutation-free
     */
    public function validationScore(): ?int;

    /**
     * Return structured validation issues reported while producing this evidence snapshot.
     *
     * @return array<array-key, mixed>|null
     *
     * @psalm-mutation-free
     */
    public function validationIssues(): ?array;

    /**
     * Return the optional digest that identifies the provider evidence behind this snapshot.
     *
     * @psalm-mutation-free
     */
    public function providerDigest(): ?string;

    /**
     * Return the immutable snapshot creation timestamp in its serialized contract form.
     *
     * @psalm-mutation-free
     */
    public function createdAt(): string;
}
