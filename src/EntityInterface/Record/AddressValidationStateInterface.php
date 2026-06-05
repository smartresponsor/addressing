<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\EntityInterface\Record;

interface AddressValidationStateInterface
{
    public function validationStatus(): string;

    public function validationProvider(): ?string;

    public function validatedAt(): ?string;

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
}
