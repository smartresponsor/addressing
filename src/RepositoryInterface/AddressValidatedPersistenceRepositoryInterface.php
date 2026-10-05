<?php

declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\Entity\AddressEvidenceSnapshotEntity;
use App\Addressing\Entity\AddressOutboxEntity;

/**
 * Defines the transactional persistence boundary used by validated address mutation workflows.
 */
interface AddressValidatedPersistenceRepositoryInterface
{
    /** Finds an address only when it is visible within the supplied ownership scope. */
    public function findScopedAddress(string $id, ?string $ownerId, ?string $vendorId): ?AddressEntity;

    /** Begins the transaction that groups one validated persistence operation atomically. */
    public function beginTransaction(): void;

    /** Commits the active validated persistence transaction after all writes succeed. */
    public function commit(): void;

    /** Rolls back the active transaction when a validated persistence operation cannot complete. */
    public function rollbackIfActive(): void;

    /** Persists validation evidence associated with the address mutation being committed. */
    public function persistEvidenceSnapshot(AddressEvidenceSnapshotEntity $snapshot): void;

    /** Persists the outbox event emitted by the validated address mutation. */
    public function persistOutbox(AddressOutboxEntity $outbox): void;

    /** Flushes pending validated-persistence writes to the configured persistence backend. */
    public function flush(): void;
}
