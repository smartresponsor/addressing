<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 */
declare(strict_types=1);

namespace App\EntityInterface\Record;

interface AddressEvidenceSnapshotInterface
{
    public function id(): string;

    public function addressId(): string;

    public function ownerId(): ?string;

    public function vendorId(): ?string;

    public function sourceSystem(): ?string;

    public function sourceType(): ?string;

    public function sourceReference(): ?string;

    public function validatedBy(): ?string;

    public function validatedAt(): ?string;

    public function normalizationVersion(): ?string;

    /** @return array<string, mixed>|null */
    public function rawInputSnapshot(): ?array;

    /** @return array<string, mixed>|null */
    public function normalizedSnapshot(): ?array;

    public function validationStatus(): string;

    public function validationScore(): ?int;

    /** @return array<string, mixed>|null */
    public function validationIssues(): ?array;

    public function providerDigest(): ?string;

    public function createdAt(): string;
}
