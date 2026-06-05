<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'address_evidence_snapshot')]
#[ORM\Index(name: 'address_evidence_snapshot_owner_idx', columns: ['owner_id'])]
#[ORM\Index(name: 'address_evidence_snapshot_vendor_idx', columns: ['vendor_id'])]
#[ORM\Index(name: 'address_evidence_snapshot_address_idx', columns: ['address_id', 'created_at', 'id'])]
class AddressEvidenceSnapshotEntity
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 32, unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: AddressEntity::class)]
    #[ORM\JoinColumn(name: 'address_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private AddressEntity $address;

    #[ORM\Column(name: 'owner_id', type: 'string', length: 64, nullable: true)]
    private ?string $ownerId = null;

    #[ORM\Column(name: 'vendor_id', type: 'string', length: 64, nullable: true)]
    private ?string $vendorId = null;

    #[ORM\Column(name: 'source_system', type: 'string', length: 64, nullable: true)]
    private ?string $sourceSystem = null;

    #[ORM\Column(name: 'source_type', type: 'string', length: 32, nullable: true)]
    private ?string $sourceType = null;

    #[ORM\Column(name: 'source_reference', type: 'string', length: 128, nullable: true)]
    private ?string $sourceReference = null;

    #[ORM\Column(name: 'validated_by', type: 'string', length: 64, nullable: true)]
    private ?string $validatedBy = null;

    #[ORM\Column(name: 'validated_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\Column(name: 'normalization_version', type: 'string', length: 64, nullable: true)]
    private ?string $normalizationVersion = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'raw_input_snapshot', type: 'json', nullable: true)]
    private ?array $rawInputSnapshot = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'normalized_snapshot', type: 'json', nullable: true)]
    private ?array $normalizedSnapshot = null;

    #[ORM\Column(name: 'validation_status', type: 'string', length: 16, options: ['default' => 'unknown'])]
    private string $validationStatus = 'unknown';

    #[ORM\Column(name: 'validation_score', type: 'integer', nullable: true)]
    private ?int $validationScore = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'validation_issues', type: 'json', nullable: true)]
    private ?array $validationIssues = null;

    #[ORM\Column(name: 'provider_digest', type: 'string', length: 64, nullable: true)]
    private ?string $providerDigest = null;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getAddress(): AddressEntity
    {
        return $this->address;
    }

    public function setAddress(AddressEntity $address): self
    {
        $this->address = $address;

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

    public function getValidatedBy(): ?string
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?string $validatedBy): self
    {
        $this->validatedBy = $validatedBy;

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

    public function getValidationStatus(): string
    {
        return $this->validationStatus;
    }

    public function setValidationStatus(string $validationStatus): self
    {
        $this->validationStatus = $validationStatus;

        return $this;
    }

    public function getValidationScore(): ?int
    {
        return $this->validationScore;
    }

    public function setValidationScore(?int $validationScore): self
    {
        $this->validationScore = $validationScore;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getValidationIssues(): ?array
    {
        return $this->validationIssues;
    }

    /** @param array<string, mixed>|null $validationIssues */
    public function setValidationIssues(?array $validationIssues): self
    {
        $this->validationIssues = $validationIssues;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
