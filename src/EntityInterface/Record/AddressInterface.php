<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\EntityInterface\Record;

interface AddressInterface
{
    public function id(): string;

    public function ownerId(): ?string;

    public function vendorId(): ?string;

    public function line1(): string;

    public function line2(): ?string;

    public function city(): string;

    public function region(): ?string;

    public function postalCode(): ?string;

    public function countryCode(): string;

    public function line1Norm(): ?string;

    public function cityNorm(): ?string;

    public function regionNorm(): ?string;

    public function postalCodeNorm(): ?string;

    public function latitude(): ?float;

    public function longitude(): ?float;

    public function geohash(): ?string;

    public function validationStatus(): string;

    public function validationProvider(): ?string;

    public function validatedAt(): ?string;

    public function dedupeKey(): ?string;

    public function validationFingerprint(): ?string;

    /** @return array<string, mixed>|null */
    public function validationRaw(): ?array;

    /** @return array<string, mixed>|null */
    public function validationVerdict(): ?array;

    public function validationDeliverable(): ?bool;

    public function validationGranularity(): ?string;

    public function validationQuality(): ?int;

    public function sourceSystem(): ?string;

    public function sourceType(): ?string;

    public function sourceReference(): ?string;

    public function normalizationVersion(): ?string;

    /** @return array<string, mixed>|null */
    public function rawInputSnapshot(): ?array;

    /** @return array<string, mixed>|null */
    public function normalizedSnapshot(): ?array;

    public function providerDigest(): ?string;

    public function governanceStatus(): string;

    public function duplicateOfId(): ?string;

    public function supersededById(): ?string;

    public function aliasOfId(): ?string;

    public function conflictWithId(): ?string;

    public function revalidationDueAt(): ?string;

    public function revalidationPolicy(): ?string;

    public function lastValidationProvider(): ?string;

    public function lastValidationStatus(): ?string;

    public function lastValidationScore(): ?int;

    public function createdAt(): string;

    public function updatedAt(): ?string;

    public function deletedAt(): ?string;
}
