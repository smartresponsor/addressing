<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\EntityInterface\Record;

interface AddressGovernanceStateInterface
{
    public function governanceStatus(): string;

    public function duplicateOfId(): ?string;

    public function supersededById(): ?string;

    public function aliasOfId(): ?string;

    public function conflictWithId(): ?string;
}
