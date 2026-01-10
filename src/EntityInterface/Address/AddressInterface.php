<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

namespace App\EntityInterface\Address;

/**
 *
 */

/**
 *
 */
interface AddressInterface
{
    /**
     * @return string
     */
    public function id(): string;

    /**
     * @return string|null
     */
    public function ownerId(): ?string;

    /**
     * @return string|null
     */
    public function vendorId(): ?string;

    /**
     * @return string
     */
    public function line1(): string;

    /**
     * @return string|null
     */
    public function line2(): ?string;

    /**
     * @return string
     */
    public function city(): string;

    /**
     * @return string|null
     */
    public function region(): ?string;

    /**
     * @return string|null
     */
    public function postalCode(): ?string;

    /**
     * @return string
     */
    public function countryCode(): string;

    /**
     * @return array<string, string>|null
     */
    public function line1Localized(): ?array;

    /**
     * @return array<string, string>|null
     */
    public function cityLocalized(): ?array;

    /**
     * @return string|null
     */
    public function line1Norm(): ?string;

    /**
     * @return string|null
     */
    public function cityNorm(): ?string;

    /**
     * @return string|null
     */
    public function regionNorm(): ?string;

    /**
     * @return string|null
     */
    public function postalCodeNorm(): ?string;

    /**
     * @return float|null
     */
    public function latitude(): ?float;

    /**
     * @return float|null
     */
    public function longitude(): ?float;

    /**
     * @return string|null
     */
    public function geohash(): ?string;

    /**
     * @return string
     */
    public function validationStatus(): string;

    /**
     * @return string|null
     */
    public function validationProvider(): ?string;

    /**
     * @return string|null
     */
    public function validatedAt(): ?string;

    /**
     * @return string|null
     */
    public function dedupeKey(): ?string;

    /**
     * @return string|null
     */
    public function validationFingerprint(): ?string;
    /** @return array<string, mixed>|null */
    public function validationRaw(): ?array;
    /** @return array<string, mixed>|null */
    public function validationVerdict(): ?array;

    /**
     * @return bool|null
     */
    public function validationDeliverable(): ?bool;

    /**
     * @return string|null
     */
    public function validationGranularity(): ?string;

    /**
     * @return int|null
     */
    public function validationQuality(): ?int;

    /**
     * @return string
     */
    public function createdAt(): string;

    /**
     * @return string|null
     */
    public function updatedAt(): ?string;

    /**
     * @return string|null
     */
    public function deletedAt(): ?string;
}
