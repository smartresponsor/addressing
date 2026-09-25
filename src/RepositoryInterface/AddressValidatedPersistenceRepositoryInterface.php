<?php

declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\Entity\AddressEvidenceSnapshotEntity;
use App\Addressing\Entity\AddressOutboxEntity;

interface AddressValidatedPersistenceRepositoryInterface
{
    public function findScopedAddress(string $id, ?string $ownerId, ?string $vendorId): ?AddressEntity;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollbackIfActive(): void;

    public function persistEvidenceSnapshot(AddressEvidenceSnapshotEntity $snapshot): void;

    public function persistOutbox(AddressOutboxEntity $outbox): void;

    public function flush(): void;
}
