<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'address_entity')]
#[ORM\Index(name: 'address_owner_idx', columns: ['owner_id'])]
#[ORM\Index(name: 'address_vendor_idx', columns: ['vendor_id'])]
#[ORM\Index(name: 'address_country_idx', columns: ['country_code'])]
#[ORM\Index(name: 'address_city_idx', columns: ['city'])]
#[ORM\Index(name: 'address_status_idx', columns: ['validation_status'])]
#[ORM\Index(name: 'address_governance_status_idx', columns: ['governance_status'])]
#[ORM\Index(name: 'address_revalidation_due_at_idx', columns: ['revalidation_due_at'])]
#[ORM\Index(name: 'address_last_validation_status_idx', columns: ['last_validation_status'])]
#[ORM\Index(name: 'address_validation_fp_idx', columns: ['validation_fingerprint'])]
#[ORM\UniqueConstraint(name: 'address_dedupe_unique', columns: ['dedupe_key'])]
class AddressEntity
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 26, unique: true)]
    private string $id;

    #[ORM\Column(name: 'owner_id', type: 'string', length: 64, nullable: true)]
    private ?string $ownerId = null;

    #[ORM\Column(name: 'vendor_id', type: 'string', length: 64, nullable: true)]
    private ?string $vendorId = null;

    #[ORM\Column(name: 'line1', type: 'string', length: 256)]
    private string $line1;

    #[ORM\Column(name: 'line2', type: 'string', length: 256, nullable: true)]
    private ?string $line2 = null;

    #[ORM\Column(name: 'city', type: 'string', length: 128)]
    private string $city;

    #[ORM\Column(name: 'region', type: 'string', length: 128, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(name: 'postal_code', type: 'string', length: 32, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(name: 'country_code', type: 'string', length: 2)]
    private string $countryCode;

    #[ORM\Column(name: 'line1_norm', type: 'string', length: 256, nullable: true)]
    private ?string $line1Norm = null;

    #[ORM\Column(name: 'city_norm', type: 'string', length: 128, nullable: true)]
    private ?string $cityNorm = null;

    #[ORM\Column(name: 'region_norm', type: 'string', length: 128, nullable: true)]
    private ?string $regionNorm = null;

    #[ORM\Column(name: 'postal_code_norm', type: 'string', length: 32, nullable: true)]
    private ?string $postalCodeNorm = null;

    #[ORM\Column(name: 'latitude', type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(name: 'longitude', type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(name: 'geohash', type: 'string', length: 32, nullable: true)]
    private ?string $geohash = null;

    #[ORM\Column(name: 'validation_status', type: 'string', length: 16, options: ['default' => 'unknown'])]
    private string $validationStatus = 'unknown';

    #[ORM\Column(name: 'validation_provider', type: 'string', length: 64, nullable: true)]
    private ?string $validationProvider = null;

    #[ORM\Column(name: 'validated_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\Column(name: 'dedupe_key', type: 'string', length: 128, nullable: true)]
    private ?string $dedupeKey = null;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'deleted_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(name: 'validation_fingerprint', type: 'string', length: 64, nullable: true)]
    private ?string $validationFingerprint = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'validation_raw', type: 'json', nullable: true)]
    private ?array $validationRaw = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'validation_verdict', type: 'json', nullable: true)]
    private ?array $validationVerdict = null;

    #[ORM\Column(name: 'validation_deliverable', type: 'boolean', nullable: true)]
    private ?bool $validationDeliverable = null;

    #[ORM\Column(name: 'validation_granularity', type: 'string', length: 64, nullable: true)]
    private ?string $validationGranularity = null;

    #[ORM\Column(name: 'validation_quality', type: 'integer', nullable: true)]
    private ?int $validationQuality = null;

    #[ORM\Column(name: 'source_system', type: 'string', length: 64, nullable: true)]
    private ?string $sourceSystem = null;

    #[ORM\Column(name: 'source_type', type: 'string', length: 32, nullable: true)]
    private ?string $sourceType = null;

    #[ORM\Column(name: 'source_reference', type: 'string', length: 128, nullable: true)]
    private ?string $sourceReference = null;

    #[ORM\Column(name: 'normalization_version', type: 'string', length: 64, nullable: true)]
    private ?string $normalizationVersion = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'raw_input_snapshot', type: 'json', nullable: true)]
    private ?array $rawInputSnapshot = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'normalized_snapshot', type: 'json', nullable: true)]
    private ?array $normalizedSnapshot = null;

    #[ORM\Column(name: 'provider_digest', type: 'string', length: 64, nullable: true)]
    private ?string $providerDigest = null;

    #[ORM\Column(name: 'governance_status', type: 'string', length: 16, options: ['default' => 'canonical'])]
    private string $governanceStatus = 'canonical';

    #[ORM\Column(name: 'duplicate_of_id', type: 'string', length: 26, nullable: true)]
    private ?string $duplicateOfId = null;

    #[ORM\Column(name: 'superseded_by_id', type: 'string', length: 26, nullable: true)]
    private ?string $supersededById = null;

    #[ORM\Column(name: 'alias_of_id', type: 'string', length: 26, nullable: true)]
    private ?string $aliasOfId = null;

    #[ORM\Column(name: 'conflict_with_id', type: 'string', length: 26, nullable: true)]
    private ?string $conflictWithId = null;

    #[ORM\Column(name: 'revalidation_due_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $revalidationDueAt = null;

    #[ORM\Column(name: 'revalidation_policy', type: 'string', length: 32, nullable: true)]
    private ?string $revalidationPolicy = null;

    #[ORM\Column(name: 'last_validation_provider', type: 'string', length: 64, nullable: true)]
    private ?string $lastValidationProvider = null;

    #[ORM\Column(name: 'last_validation_status', type: 'string', length: 16, nullable: true)]
    private ?string $lastValidationStatus = null;

    #[ORM\Column(name: 'last_validation_score', type: 'integer', nullable: true)]
    private ?int $lastValidationScore = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getOwnerId(): ?string
    {
        return $this->ownerId;
    }

    public function setOwnerId(?string $ownerId): self
    {
        $this->ownerId = $ownerId;

        return $this;
    }

    public function getVendorId(): ?string
    {
        return $this->vendorId;
    }

    public function setVendorId(?string $vendorId): self
    {
        $this->vendorId = $vendorId;

        return $this;
    }

    public function getLine1(): string
    {
        return $this->line1;
    }

    public function setLine1(string $line1): self
    {
        $this->line1 = $line1;

        return $this;
    }

    public function getLine2(): ?string
    {
        return $this->line2;
    }

    public function setLine2(?string $line2): self
    {
        $this->line2 = $line2;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): self
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): self
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    public function getLine1Norm(): ?string
    {
        return $this->line1Norm;
    }

    public function setLine1Norm(?string $line1Norm): self
    {
        $this->line1Norm = $line1Norm;

        return $this;
    }

    public function getCityNorm(): ?string
    {
        return $this->cityNorm;
    }

    public function setCityNorm(?string $cityNorm): self
    {
        $this->cityNorm = $cityNorm;

        return $this;
    }

    public function getRegionNorm(): ?string
    {
        return $this->regionNorm;
    }

    public function setRegionNorm(?string $regionNorm): self
    {
        $this->regionNorm = $regionNorm;

        return $this;
    }

    public function getPostalCodeNorm(): ?string
    {
        return $this->postalCodeNorm;
    }

    public function setPostalCodeNorm(?string $postalCodeNorm): self
    {
        $this->postalCodeNorm = $postalCodeNorm;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getGeohash(): ?string
    {
        return $this->geohash;
    }

    public function setGeohash(?string $geohash): self
    {
        $this->geohash = $geohash;

        return $this;
    }

    public function getValidationStatus(): string
    {
        return $this->validationStatus;
    }

    public function setValidationStatus(string $validationStatus): self
    {
        $this->validationStatus = $validationStatus;

        return $this;
    }

    public function getValidationProvider(): ?string
    {
        return $this->validationProvider;
    }

    public function setValidationProvider(?string $validationProvider): self
    {
        $this->validationProvider = $validationProvider;

        return $this;
    }

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeImmutable $validatedAt): self
    {
        $this->validatedAt = $validatedAt;

        return $this;
    }

    public function getDedupeKey(): ?string
    {
        return $this->dedupeKey;
    }

    public function setDedupeKey(?string $dedupeKey): self
    {
        $this->dedupeKey = $dedupeKey;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->getCreatedAt();
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->getUpdatedAt();
    }

    public function modifiedAt(): ?\DateTimeImmutable
    {
        return $this->getUpdatedAt();
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function deletedAt(): ?\DateTimeImmutable
    {
        return $this->getDeletedAt();
    }

    public function getValidationFingerprint(): ?string
    {
        return $this->validationFingerprint;
    }

    public function setValidationFingerprint(?string $validationFingerprint): self
    {
        $this->validationFingerprint = $validationFingerprint;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getValidationRaw(): ?array
    {
        return $this->validationRaw;
    }

    /** @param array<string, mixed>|null $validationRaw */
    public function setValidationRaw(?array $validationRaw): self
    {
        $this->validationRaw = $validationRaw;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getValidationVerdict(): ?array
    {
        return $this->validationVerdict;
    }

    /** @param array<string, mixed>|null $validationVerdict */
    public function setValidationVerdict(?array $validationVerdict): self
    {
        $this->validationVerdict = $validationVerdict;

        return $this;
    }

    public function getValidationDeliverable(): ?bool
    {
        return $this->validationDeliverable;
    }

    public function setValidationDeliverable(?bool $validationDeliverable): self
    {
        $this->validationDeliverable = $validationDeliverable;

        return $this;
    }

    public function getValidationGranularity(): ?string
    {
        return $this->validationGranularity;
    }

    public function setValidationGranularity(?string $validationGranularity): self
    {
        $this->validationGranularity = $validationGranularity;

        return $this;
    }

    public function getValidationQuality(): ?int
    {
        return $this->validationQuality;
    }

    public function setValidationQuality(?int $validationQuality): self
    {
        $this->validationQuality = $validationQuality;

        return $this;
    }

    public function getSourceSystem(): ?string
    {
        return $this->sourceSystem;
    }

    public function setSourceSystem(?string $sourceSystem): self
    {
        $this->sourceSystem = $sourceSystem;

        return $this;
    }

    public function getSourceType(): ?string
    {
        return $this->sourceType;
    }

    public function setSourceType(?string $sourceType): self
    {
        $this->sourceType = $sourceType;

        return $this;
    }

    public function getSourceReference(): ?string
    {
        return $this->sourceReference;
    }

    public function setSourceReference(?string $sourceReference): self
    {
        $this->sourceReference = $sourceReference;

        return $this;
    }

    public function getNormalizationVersion(): ?string
    {
        return $this->normalizationVersion;
    }

    public function setNormalizationVersion(?string $normalizationVersion): self
    {
        $this->normalizationVersion = $normalizationVersion;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getRawInputSnapshot(): ?array
    {
        return $this->rawInputSnapshot;
    }

    /** @param array<string, mixed>|null $rawInputSnapshot */
    public function setRawInputSnapshot(?array $rawInputSnapshot): self
    {
        $this->rawInputSnapshot = $rawInputSnapshot;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getNormalizedSnapshot(): ?array
    {
        return $this->normalizedSnapshot;
    }

    /** @param array<string, mixed>|null $normalizedSnapshot */
    public function setNormalizedSnapshot(?array $normalizedSnapshot): self
    {
        $this->normalizedSnapshot = $normalizedSnapshot;

        return $this;
    }

    public function getProviderDigest(): ?string
    {
        return $this->providerDigest;
    }

    public function setProviderDigest(?string $providerDigest): self
    {
        $this->providerDigest = $providerDigest;

        return $this;
    }

    public function getGovernanceStatus(): string
    {
        return $this->governanceStatus;
    }

    public function setGovernanceStatus(string $governanceStatus): self
    {
        $this->governanceStatus = $governanceStatus;

        return $this;
    }

    public function getDuplicateOfId(): ?string
    {
        return $this->duplicateOfId;
    }

    public function setDuplicateOfId(?string $duplicateOfId): self
    {
        $this->duplicateOfId = $duplicateOfId;

        return $this;
    }

    public function getSupersededById(): ?string
    {
        return $this->supersededById;
    }

    public function setSupersededById(?string $supersededById): self
    {
        $this->supersededById = $supersededById;

        return $this;
    }

    public function getAliasOfId(): ?string
    {
        return $this->aliasOfId;
    }

    public function setAliasOfId(?string $aliasOfId): self
    {
        $this->aliasOfId = $aliasOfId;

        return $this;
    }

    public function getConflictWithId(): ?string
    {
        return $this->conflictWithId;
    }

    public function setConflictWithId(?string $conflictWithId): self
    {
        $this->conflictWithId = $conflictWithId;

        return $this;
    }

    public function getRevalidationDueAt(): ?\DateTimeImmutable
    {
        return $this->revalidationDueAt;
    }

    public function setRevalidationDueAt(?\DateTimeImmutable $revalidationDueAt): self
    {
        $this->revalidationDueAt = $revalidationDueAt;

        return $this;
    }

    public function getRevalidationPolicy(): ?string
    {
        return $this->revalidationPolicy;
    }

    public function setRevalidationPolicy(?string $revalidationPolicy): self
    {
        $this->revalidationPolicy = $revalidationPolicy;

        return $this;
    }

    public function getLastValidationProvider(): ?string
    {
        return $this->lastValidationProvider;
    }

    public function setLastValidationProvider(?string $lastValidationProvider): self
    {
        $this->lastValidationProvider = $lastValidationProvider;

        return $this;
    }

    public function getLastValidationStatus(): ?string
    {
        return $this->lastValidationStatus;
    }

    public function setLastValidationStatus(?string $lastValidationStatus): self
    {
        $this->lastValidationStatus = $lastValidationStatus;

        return $this;
    }

    public function getLastValidationScore(): ?int
    {
        return $this->lastValidationScore;
    }

    public function setLastValidationScore(?int $lastValidationScore): self
    {
        $this->lastValidationScore = $lastValidationScore;

        return $this;
    }

    public function delete(?\DateTimeImmutable $deletedAt = null): self
    {
        $this->deletedAt = $deletedAt ?? new \DateTimeImmutable();

        return $this;
    }

    public function restore(): self
    {
        $this->deletedAt = null;

        return $this;
    }
}
