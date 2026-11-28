<?php

declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

/**
 * Manages the persistence schema lifecycle required by the Addressing component.
 *
 * @psalm-mutable
 */
interface AddressSchemaRepositoryInterface
{
    /**
     * Ensures the Addressing persistence schema exists and is ready for repository operations.
     *
     * @psalm-impure
     */
    public function ensureSchema(): void;

    /**
     * Resets the Addressing persistence schema to a clean component-owned state.
     *
     * @psalm-impure
     */
    public function resetSchema(): void;
}
