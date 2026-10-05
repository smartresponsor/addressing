<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Contract;

/**
 * Exposes the governance classification and relationship links for one address record.
 *
 * The contract keeps duplicate, supersession, alias, and conflict decisions explicit
 * without coupling callers to the persistence representation that stores those links.
 */
interface AddressGovernanceStateInterface
{
    /** Return the canonical governance status assigned to the address. */
    public function governanceStatus(): string;

    /** Return the canonical address identifier this record duplicates, when classified as a duplicate. */
    public function duplicateOfId(): ?string;

    /** Return the canonical address identifier that supersedes this record, when one is known. */
    public function supersededById(): ?string;

    /** Return the canonical address identifier represented by this alias, when aliasing is established. */
    public function aliasOfId(): ?string;

    /** Return the canonical address identifier in governance conflict with this record, when applicable. */
    public function conflictWithId(): ?string;
}
