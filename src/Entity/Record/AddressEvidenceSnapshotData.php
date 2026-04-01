<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 */
declare(strict_types=1);

namespace App\Entity\Record;

use App\EntityInterface\Record\AddressEvidenceSnapshotInterface;

final class AddressEvidenceSnapshotData implements AddressEvidenceSnapshotInterface
{
    /**
     * @param array<string, mixed>|null $rawInputSnapshot
     * @param array<string, mixed>|null $normalizedSnapshot
     * @param array<string, mixed>|null $validationIssues
     */
    public function __construct(
        public string $id,
        public string $addressId,
        public ?string $ownerId,
        public ?string $vendorId,
        public ?string $sourceSystem,
        public ?string $sourceType,
        public ?string $sourceReference,
        public ?string $validatedBy,
        public ?string $validatedAt,
        public ?string $normalizationVersion,
        public ?array $rawInputSnapshot,
        public ?array $normalizedSnapshot,
        public string $validationStatus,
        public ?int $validationScore,
        public ?array $validationIssues,
        public ?string $providerDigest,
        public string $createdAt,
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

    #[\Override]
    public function rawInputSnapshot(): ?array
    {
        return $this->rawInputSnapshot;
    }

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
