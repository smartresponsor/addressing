<?php

declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

/**
 * Manages the persistence schema lifecycle required by the Addressing component.
 */
interface AddressSchemaRepositoryInterface
{
    /** Ensures the Addressing persistence schema exists and is ready for repository operations. */
    public function ensureSchema(): void;

    /** Resets the Addressing persistence schema to a clean component-owned state. */
    public function resetSchema(): void;
}
