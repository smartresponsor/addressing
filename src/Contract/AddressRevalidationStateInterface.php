<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Contract;

/**
 * Describes the persisted revalidation schedule and latest provider outcome for an address.
 *
 * The contract keeps lifecycle timing and prior validation evidence available to orchestration
 * code without coupling callers to a concrete Addressing entity or external provider client.
 */
interface AddressRevalidationStateInterface
{
    /** Return the scheduled revalidation instant in the component's serialized timestamp form. */
    public function revalidationDueAt(): ?string;

    /** Return the policy identifier that determined the current revalidation schedule. */
    public function revalidationPolicy(): ?string;

    /** Return the provider identifier used for the most recently recorded validation attempt. */
    public function lastValidationProvider(): ?string;

    /** Return the status produced by the most recently recorded validation attempt. */
    public function lastValidationStatus(): ?string;

    /** Return the optional quality or confidence score from the latest validation attempt. */
    public function lastValidationScore(): ?int;
}
