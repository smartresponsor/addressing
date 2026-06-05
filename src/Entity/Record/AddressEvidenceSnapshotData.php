<?php

declare(strict_types=1);

namespace App\Entity\Record;

use App\EntityInterface\Record\AddressEvidenceSnapshotInterface;

final readonly class AddressEvidenceSnapshotData implements AddressEvidenceSnapshotInterface
{
    /**
     * @param array<string, mixed>|null $rawInputSnapshot
     * @param array<string, mixed>|null $normalizedSnapshot
     * @param array<string, mixed>|null $validationIssues
     */
    public function __construct(
        private string $id,
        private string $addressId,
        private ?string $ownerId,
        private ?string $vendorId,
        private ?string $sourceSystem,
        private ?string $sourceType,
        private ?string $sourceReference,
        private ?string $validatedBy,
        private ?string $validatedAt,
        private ?string $normalizationVersion,
        private ?array $rawInputSnapshot,
        private ?array $normalizedSnapshot,
        private string $validationStatus,
        private ?int $validationScore,
        private ?array $validationIssues,
        private ?string $providerDigest,
        private string $createdAt,
    ) {
    }

    #[\Override]
    public function id(): string
    {
        return $this->id;
    }

    #[\Override]
    public function addressId(): string
    {
        return $this->addressId;
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
    public function validatedBy(): ?string
    {
        return $this->validatedBy;
    }

    #[\Override]
    public function validatedAt(): ?string
    {
        return $this->validatedAt;
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
    public function validationStatus(): string
    {
        return $this->validationStatus;
    }

    #[\Override]
    public function validationScore(): ?int
    {
        return $this->validationScore;
    }

    /** @return array<string, mixed>|null */
    #[\Override]
    public function validationIssues(): ?array
    {
        return $this->validationIssues;
    }

    #[\Override]
    public function providerDigest(): ?string
    {
        return $this->providerDigest;
    }

    #[\Override]
    public function createdAt(): string
    {
        return $this->createdAt;
    }
}
