<?php

declare(strict_types=1);

namespace App\Addressing\Context\Persistence;

use App\Addressing\Contract\Message\AddressValidated;
use App\Addressing\Plan\Persistence\AddressValidatedMutationPlan;

/**
 * Carries the immutable validation evidence needed to persist one Addressing snapshot.
 *
 * The context keeps persistence inputs explicit while leaving mutation-plan calculation
 * and evidence-entity construction in their owning application and persistence services.
 */
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

    /**
     * Projects validated-event data and the calculated mutation plan into snapshot persistence inputs.
     */
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
