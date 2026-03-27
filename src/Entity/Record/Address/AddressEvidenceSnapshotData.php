<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 */
declare(strict_types=1);

namespace App\Entity\Record\Address;

use App\EntityInterface\Record\Address\AddressEvidenceSnapshotInterface;

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

    public function id(): string
    {
        return $this->id;
    }

    public function addressId(): string
    {
        return $this->addressId;
    }

    public function ownerId(): ?string
    {
        return $this->ownerId;
    }

    public function vendorId(): ?string
    {
        return $this->vendorId;
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

    public function validatedBy(): ?string
    {
        return $this->validatedBy;
    }

    public function validatedAt(): ?string
    {
        return $this->validatedAt;
    }

    public function normalizationVersion(): ?string
    {
        return $this->normalizationVersion;
    }

    public function rawInputSnapshot(): ?array
    {
        return $this->rawInputSnapshot;
    }

    public function normalizedSnapshot(): ?array
    {
        return $this->normalizedSnapshot;
    }

    public function validationStatus(): string
    {
        return $this->validationStatus;
    }

    public function validationScore(): ?int
    {
        return $this->validationScore;
    }

    public function validationIssues(): ?array
    {
        return $this->validationIssues;
    }

    public function providerDigest(): ?string
    {
        return $this->providerDigest;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }
}
