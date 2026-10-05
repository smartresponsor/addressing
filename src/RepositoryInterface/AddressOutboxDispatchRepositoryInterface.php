<?php

declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

/**
 * Coordinates reservation and delivery-state transitions for persisted address outbox events.
 */
interface AddressOutboxDispatchRepositoryInterface
{
    /**
     * Reserves a bounded batch of unpublished outbox rows for one dispatcher lock owner.
     *
     * @return array<int, array<string, mixed>>
     */
    public function reserve(string $lockId, int $limit): array;

    /** Marks a reserved outbox row as successfully published by the dispatcher. */
    public function markPublished(int $id): void;

    /** Records the latest dispatch failure details for a persisted outbox row. */
    public function markDispatchFailure(int $id, ?string $error): void;
}
