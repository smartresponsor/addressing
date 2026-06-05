<?php

declare(strict_types=1);

namespace App\Entity\Record;

use App\EntityInterface\Record\AddressGovernanceStateInterface;
use App\EntityInterface\Record\AddressInterface;
use App\EntityInterface\Record\AddressRevalidationStateInterface;
use App\EntityInterface\Record\AddressValidationStateInterface;

final readonly class AddressData implements AddressInterface
{
    /**
     * @param array<string, mixed>|null $validationRaw
     * @param array<string, mixed>|null $validationVerdict
     * @param array<string, mixed>|null $rawInputSnapshot
     * @param array<string, mixed>|null $normalizedSnapshot
     */
    public function __construct(
        private string $id,
        private ?string $ownerId,
        private ?string $vendorId,
        private string $line1,
        private ?string $line2,
        private string $city,
        private ?string $region,
        private ?string $postalCode,
        private string $countryCode,
        private ?string $line1Norm,
        private ?string $cityNorm,
        private ?string $regionNorm,
        private ?string $postalCodeNorm,
        private ?float $latitude,
        private ?float $longitude,
        private ?string $geohash,
        private string $validationStatus,
        private ?string $validationProvider,
        private ?string $validatedAt,
        private ?string $dedupeKey,
        private string $createdAt,
        private ?string $updatedAt,
        private ?string $deletedAt,
        private ?string $validationFingerprint,
        private ?array $validationRaw,
        private ?array $validationVerdict,
        private ?bool $validationDeliverable,
        private ?string $validationGranularity,
        private ?int $validationQuality,
        private ?string $sourceSystem,
        private ?string $sourceType,
        private ?string $sourceReference,
        private ?string $normalizationVersion,
        private ?array $rawInputSnapshot,
        private ?array $normalizedSnapshot,
        private ?string $providerDigest,
        private string $governanceStatus,
        private ?string $duplicateOfId,
        private ?string $supersededById,
        private ?string $aliasOfId,
        private ?string $conflictWithId,
        private ?string $revalidationDueAt,
        private ?string $revalidationPolicy,
        private ?string $lastValidationProvider,
        private ?string $lastValidationStatus,
        private ?int $lastValidationScore,
    ) {
    }

    #[\Override]
    public function validationState(): AddressValidationStateInterface
    {
        return new AddressValidationState(
            $this->validationStatus,
            $this->validationProvider,
            $this->validatedAt,
            $this->validationFingerprint,
            $this->validationRaw,
            $this->validationVerdict,
            $this->validationDeliverable,
            $this->validationGranularity,
            $this->validationQuality,
            $this->sourceSystem,
            $this->sourceType,
            $this->sourceReference,
            $this->normalizationVersion,
            $this->rawInputSnapshot,
            $this->normalizedSnapshot,
            $this->providerDigest,
        );
    }

    #[\Override]
    public function governanceState(): AddressGovernanceStateInterface
    {
        return new AddressGovernanceState(
            $this->governanceStatus,
            $this->duplicateOfId,
            $this->supersededById,
            $this->aliasOfId,
            $this->conflictWithId,
        );
    }

    #[\Override]
    public function revalidationState(): AddressRevalidationStateInterface
    {
        return new AddressRevalidationState(
            $this->revalidationDueAt,
            $this->revalidationPolicy,
            $this->lastValidationProvider,
            $this->lastValidationStatus,
            $this->lastValidationScore,
        );
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

    /** @return array<string, mixed>|null */
    #[\Override]
    public function validationRaw(): ?array
    {
        return $this->validationRaw;
    }

    /** @return array<string, mixed>|null */
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
