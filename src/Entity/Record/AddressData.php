<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Entity\Record;

use App\EntityInterface\Record\AddressInterface;

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

    #[\Override]
    public function id(): string
    {
        return $this->id;
    }

    #[\Override]
    public function ownerId(): ?string
    {
        return $this->ownerId;
    }

    #[\Override]
    public function vendorId(): ?string
    {
        return $this->vendorId;
    }

    #[\Override]
    public function line1(): string
    {
        return $this->line1;
    }

    #[\Override]
    public function line2(): ?string
    {
        return $this->line2;
    }

    #[\Override]
    public function city(): string
    {
        return $this->city;
    }

    #[\Override]
    public function region(): ?string
    {
        return $this->region;
    }

    #[\Override]
    public function postalCode(): ?string
    {
        return $this->postalCode;
    }

    #[\Override]
    public function countryCode(): string
    {
        return $this->countryCode;
    }

    #[\Override]
    public function line1Norm(): ?string
    {
        return $this->line1Norm;
    }

    #[\Override]
    public function cityNorm(): ?string
    {
        return $this->cityNorm;
    }

    #[\Override]
    public function regionNorm(): ?string
    {
        return $this->regionNorm;
    }

    #[\Override]
    public function postalCodeNorm(): ?string
    {
        return $this->postalCodeNorm;
    }

    #[\Override]
    public function latitude(): ?float
    {
        return $this->latitude;
    }

    #[\Override]
    public function longitude(): ?float
    {
        return $this->longitude;
    }

    #[\Override]
    public function geohash(): ?string
    {
        return $this->geohash;
    }

    #[\Override]
    public function validationStatus(): string
    {
        return $this->validationStatus;
    }

    #[\Override]
    public function validationProvider(): ?string
    {
        return $this->validationProvider;
    }

    #[\Override]
    public function validatedAt(): ?string
    {
        return $this->validatedAt;
    }

    #[\Override]
    public function dedupeKey(): ?string
    {
        return $this->dedupeKey;
    }

    #[\Override]
    public function validationFingerprint(): ?string
    {
        return $this->validationFingerprint;
    }

    /**
     * @return array<string, mixed>|null
     */
    #[\Override]
    public function validationRaw(): ?array
    {
        return $this->validationRaw;
    }

    /**
     * @return array<string, mixed>|null
     */
    #[\Override]
    public function validationVerdict(): ?array
    {
        return $this->validationVerdict;
    }

    #[\Override]
    public function validationDeliverable(): ?bool
    {
        return $this->validationDeliverable;
    }

    #[\Override]
    public function validationGranularity(): ?string
    {
        return $this->validationGranularity;
    }

    #[\Override]
    public function validationQuality(): ?int
    {
        return $this->validationQuality;
    }

    #[\Override]
    public function sourceSystem(): ?string
    {
        return $this->sourceSystem;
    }

    #[\Override]
    public function sourceType(): ?string
    {
        return $this->sourceType;
    }

    #[\Override]
    public function sourceReference(): ?string
    {
        return $this->sourceReference;
    }

    #[\Override]
    public function normalizationVersion(): ?string
    {
        return $this->normalizationVersion;
    }

    /** @return array<string, mixed>|null */
    #[\Override]
    public function rawInputSnapshot(): ?array
    {
        return $this->rawInputSnapshot;
    }

    /** @return array<string, mixed>|null */
    #[\Override]
    public function normalizedSnapshot(): ?array
    {
        return $this->normalizedSnapshot;
    }

    #[\Override]
    public function providerDigest(): ?string
    {
        return $this->providerDigest;
    }

    #[\Override]
    public function governanceStatus(): string
    {
        return $this->governanceStatus;
    }

    #[\Override]
    public function duplicateOfId(): ?string
    {
        return $this->duplicateOfId;
    }

    #[\Override]
    public function supersededById(): ?string
    {
        return $this->supersededById;
    }

    #[\Override]
    public function aliasOfId(): ?string
    {
        return $this->aliasOfId;
    }

    #[\Override]
    public function conflictWithId(): ?string
    {
        return $this->conflictWithId;
    }

    #[\Override]
    public function revalidationDueAt(): ?string
    {
        return $this->revalidationDueAt;
    }

    #[\Override]
    public function revalidationPolicy(): ?string
    {
        return $this->revalidationPolicy;
    }

    #[\Override]
    public function lastValidationProvider(): ?string
    {
        return $this->lastValidationProvider;
    }

    #[\Override]
    public function lastValidationStatus(): ?string
    {
        return $this->lastValidationStatus;
    }

    #[\Override]
    public function lastValidationScore(): ?int
    {
        return $this->lastValidationScore;
    }

    #[\Override]
    public function createdAt(): string
    {
        return $this->createdAt;
    }

    #[\Override]
    public function updatedAt(): ?string
    {
        return $this->updatedAt;
    }

    #[\Override]
    public function deletedAt(): ?string
    {
        return $this->deletedAt;
    }
}
