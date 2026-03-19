<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 */
declare(strict_types=1);

namespace App\Entity\Record\Address;

use App\EntityInterface\Record\Address\AddressInterface;

final class AddressData implements AddressInterface
{
    public function __construct(
        public string $id,
        public ?string $ownerId,
        public ?string $vendorId,
        public string $line1,
        public ?string $line2,
        public string $city,
        public ?string $region,
        public ?string $postalCode,
        public string $countryCode,
        public ?string $line1Norm,
        public ?string $cityNorm,
        public ?string $regionNorm,
        public ?string $postalCodeNorm,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $geohash,
        public string $validationStatus,
        public ?string $validationProvider,
        public ?string $validatedAt,
        public ?string $dedupeKey,
        public string $createdAt,
        public ?string $updatedAt,
        public ?string $deletedAt,
        public ?string $validationFingerprint = null,
        /** @var array<string, mixed>|null */
        public ?array $validationRaw = null,
        /** @var array<string, mixed>|null */
        public ?array $validationVerdict = null,
        public ?bool $validationDeliverable = null,
        public ?string $validationGranularity = null,
        public ?int $validationQuality = null,
        public ?string $sourceSystem = null,
        public ?string $sourceType = null,
        public ?string $sourceReference = null,
        public ?string $normalizationVersion = null,
        /** @var array<string, mixed>|null */
        public ?array $rawInputSnapshot = null,
        /** @var array<string, mixed>|null */
        public ?array $normalizedSnapshot = null,
        public ?string $providerDigest = null,
        public string $governanceStatus = 'canonical',
        public ?string $duplicateOfId = null,
        public ?string $supersededById = null,
        public ?string $aliasOfId = null,
        public ?string $conflictWithId = null,
        public ?string $revalidationDueAt = null,
        public ?string $revalidationPolicy = null,
        public ?string $lastValidationProvider = null,
        public ?string $lastValidationStatus = null,
        public ?int $lastValidationScore = null,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function ownerId(): ?string
    {
        return $this->ownerId;
    }

    public function vendorId(): ?string
    {
        return $this->vendorId;
    }

    public function line1(): string
    {
        return $this->line1;
    }

    public function line2(): ?string
    {
        return $this->line2;
    }

    public function city(): string
    {
        return $this->city;
    }

    public function region(): ?string
    {
        return $this->region;
    }

    public function postalCode(): ?string
    {
        return $this->postalCode;
    }

    public function countryCode(): string
    {
        return $this->countryCode;
    }

    public function line1Norm(): ?string
    {
        return $this->line1Norm;
    }

    public function cityNorm(): ?string
    {
        return $this->cityNorm;
    }

    public function regionNorm(): ?string
    {
        return $this->regionNorm;
    }

    public function postalCodeNorm(): ?string
    {
        return $this->postalCodeNorm;
    }

    public function latitude(): ?float
    {
        return $this->latitude;
    }

    public function longitude(): ?float
    {
        return $this->longitude;
    }

    public function geohash(): ?string
    {
        return $this->geohash;
    }

    public function validationStatus(): string
    {
        return $this->validationStatus;
    }

    public function validationProvider(): ?string
    {
        return $this->validationProvider;
    }

    public function validatedAt(): ?string
    {
        return $this->validatedAt;
    }

    public function dedupeKey(): ?string
    {
        return $this->dedupeKey;
    }

    public function validationFingerprint(): ?string
    {
        return $this->validationFingerprint;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validationRaw(): ?array
    {
        return $this->validationRaw;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validationVerdict(): ?array
    {
        return $this->validationVerdict;
    }

    public function validationDeliverable(): ?bool
    {
        return $this->validationDeliverable;
    }

    public function validationGranularity(): ?string
    {
        return $this->validationGranularity;
    }

    public function validationQuality(): ?int
    {
        return $this->validationQuality;
    }

    public function sourceSystem(): ?string
    {
        return $this->sourceSystem;
    }

    public function sourceType(): ?string
    {
        return $this->sourceType;
    }

    public function sourceReference(): ?string
    {
        return $this->sourceReference;
    }

    public function normalizationVersion(): ?string
    {
        return $this->normalizationVersion;
    }

    /** @return array<string, mixed>|null */
    public function rawInputSnapshot(): ?array
    {
        return $this->rawInputSnapshot;
    }

    /** @return array<string, mixed>|null */
    public function normalizedSnapshot(): ?array
    {
        return $this->normalizedSnapshot;
    }

    public function providerDigest(): ?string
    {
        return $this->providerDigest;
    }

    public function governanceStatus(): string
    {
        return $this->governanceStatus;
    }

    public function duplicateOfId(): ?string
    {
        return $this->duplicateOfId;
    }

    public function supersededById(): ?string
    {
        return $this->supersededById;
    }

    public function aliasOfId(): ?string
    {
        return $this->aliasOfId;
    }

    public function conflictWithId(): ?string
    {
        return $this->conflictWithId;
    }

    public function revalidationDueAt(): ?string
    {
        return $this->revalidationDueAt;
    }

    public function revalidationPolicy(): ?string
    {
        return $this->revalidationPolicy;
    }

    public function lastValidationProvider(): ?string
    {
        return $this->lastValidationProvider;
    }

    public function lastValidationStatus(): ?string
    {
        return $this->lastValidationStatus;
    }

    public function lastValidationScore(): ?int
    {
        return $this->lastValidationScore;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?string
    {
        return $this->deletedAt;
    }
}
