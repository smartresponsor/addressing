<?php

declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

/**
 * Persists and evaluates bounded request counters used by address-facing rate-limit policies.
 */
interface AddressRateLimitRepositoryInterface
{
    /** Increments the scoped counter and reports whether the resulting count remains allowed. */
    public function checkAndIncrement(string $client, string $key, int $effectiveLimit): bool;
}
