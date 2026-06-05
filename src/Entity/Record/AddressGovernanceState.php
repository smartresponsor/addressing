<?php

declare(strict_types=1);

namespace App\Entity\Record;

use App\EntityInterface\Record\AddressGovernanceStateInterface;

final readonly class AddressGovernanceState implements AddressGovernanceStateInterface
{
    public function __construct(
        private string $governanceStatus,
        private ?string $duplicateOfId,
        private ?string $supersededById,
        private ?string $aliasOfId,
        private ?string $conflictWithId,
    ) {
    }

    #[\Override]
    public function governanceStatus(): string
    {
        return $this->governanceStatus;
    }

    #[\Override]
    public function duplicateOfId(): ?string
    {
        return $this->duplicateOfId;
    }

    #[\Override]
    public function supersededById(): ?string
    {
        return $this->supersededById;
    }

    #[\Override]
    public function aliasOfId(): ?string
    {
        return $this->aliasOfId;
    }

    #[\Override]
    public function conflictWithId(): ?string
    {
        return $this->conflictWithId;
    }
}
