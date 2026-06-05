<?php

declare(strict_types=1);

namespace App\Entity\Record;

use App\EntityInterface\Record\AddressRevalidationStateInterface;

final readonly class AddressRevalidationState implements AddressRevalidationStateInterface
{
    public function __construct(
        private ?string $revalidationDueAt,
        private ?string $revalidationPolicy,
        private ?string $lastValidationProvider,
        private ?string $lastValidationStatus,
        private ?int $lastValidationScore,
    ) {
    }

    #[\Override]
    public function revalidationDueAt(): ?string
    {
        return $this->revalidationDueAt;
    }

    #[\Override]
    public function revalidationPolicy(): ?string
    {
        return $this->revalidationPolicy;
    }

    #[\Override]
    public function lastValidationProvider(): ?string
    {
        return $this->lastValidationProvider;
    }

    #[\Override]
    public function lastValidationStatus(): ?string
    {
        return $this->lastValidationStatus;
    }

    #[\Override]
    public function lastValidationScore(): ?int
    {
        return $this->lastValidationScore;
    }
}
