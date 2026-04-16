<?php

declare(strict_types=1);

namespace App\Integration\Persistence;

use App\Contract\Message\AddressValidated;

final readonly class AddressEvidenceSnapshotContext
{
    /** @param array<string, mixed>|null $normalizedSnapshot */
    public function __construct(
        public string $addressId,
        public ?string $ownerId,
        public ?string $vendorId,
        public AddressValidated $addressValidated,
        public string $validationStatus,
        public ?int $validationScore,
        public ?array $normalizedSnapshot,
        public ?string $providerDigest,
    ) {
    }

    public static function fromMutationPlan(
        string $addressId,
        ?string $ownerId,
        ?string $vendorId,
        AddressValidated $addressValidated,
        AddressValidatedMutationPlan $plan,
    ): self {
        return new self(
            addressId: $addressId,
            ownerId: $ownerId,
            vendorId: $vendorId,
            addressValidated: $addressValidated,
            validationStatus: $plan->lastValidationStatus,
            validationScore: $plan->lastValidationScore,
            normalizedSnapshot: $plan->normalizedSnapshot,
            providerDigest: $plan->providerDigest,
        );
    }
}
