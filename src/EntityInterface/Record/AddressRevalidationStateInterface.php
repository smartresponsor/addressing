<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\EntityInterface\Record;

interface AddressRevalidationStateInterface
{
    public function revalidationDueAt(): ?string;

    public function revalidationPolicy(): ?string;

    public function lastValidationProvider(): ?string;

    public function lastValidationStatus(): ?string;

    public function lastValidationScore(): ?int;
}
