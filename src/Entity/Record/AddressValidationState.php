<?php

declare(strict_types=1);

namespace App\Entity\Record;

use App\EntityInterface\Record\AddressValidationStateInterface;

final readonly class AddressValidationState implements AddressValidationStateInterface
{
    /**
     * @param array<string, mixed>|null $validationRaw
     * @param array<string, mixed>|null $validationVerdict
     * @param array<string, mixed>|null $rawInputSnapshot
     * @param array<string, mixed>|null $normalizedSnapshot
     */
    public function __construct(
        private string $validationStatus,
        private ?string $validationProvider,
        private ?string $validatedAt,
        private ?string $validationFingerprint,
        private ?array $validationRaw,
        private ?array $validationVerdict,
        private ?bool $validationDeliverable,
        private ?string $validationGranularity,
        private ?int $validationQuality,
        private ?string $sourceSystem,
        private ?string $sourceType,
        private ?string $sourceReference,
        private ?string $normalizationVersion,
        private ?array $rawInputSnapshot,
        private ?array $normalizedSnapshot,
        private ?string $providerDigest,
    ) {
    }

    #[\Override]
    public function validationStatus(): string
    {
        return $this->validationStatus;
    }

    #[\Override]
    public function validationProvider(): ?string
    {
        return $this->validationProvider;
    }

    #[\Override]
    public function validatedAt(): ?string
    {
        return $this->validatedAt;
    }

    #[\Override]
    public function validationFingerprint(): ?string
    {
        return $this->validationFingerprint;
    }

    /** @return array<string, mixed>|null */
    #[\Override]
    public function validationRaw(): ?array
    {
        return $this->validationRaw;
    }

    /** @return array<string, mixed>|null */
    #[\Override]
    public function validationVerdict(): ?array
    {
        return $this->validationVerdict;
    }

    #[\Override]
    public function validationDeliverable(): ?bool
    {
        return $this->validationDeliverable;
    }

    #[\Override]
    public function validationGranularity(): ?string
    {
        return $this->validationGranularity;
    }

    #[\Override]
    public function validationQuality(): ?int
    {
        return $this->validationQuality;
    }

    #[\Override]
    public function sourceSystem(): ?string
    {
        return $this->sourceSystem;
    }

    #[\Override]
    public function sourceType(): ?string
    {
        return $this->sourceType;
    }

    #[\Override]
    public function sourceReference(): ?string
    {
        return $this->sourceReference;
    }

    #[\Override]
    public function normalizationVersion(): ?string
    {
        return $this->normalizationVersion;
    }

    /** @return array<string, mixed>|null */
    #[\Override]
    public function rawInputSnapshot(): ?array
    {
        return $this->rawInputSnapshot;
    }

    /** @return array<string, mixed>|null */
    #[\Override]
    public function normalizedSnapshot(): ?array
    {
        return $this->normalizedSnapshot;
    }

    #[\Override]
    public function providerDigest(): ?string
    {
        return $this->providerDigest;
    }
}
