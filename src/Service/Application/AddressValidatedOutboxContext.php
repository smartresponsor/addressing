<?php

declare(strict_types=1);

namespace App\Service\Application;

use App\Integration\Persistence\AddressValidatedMutationPlan;

final readonly class AddressValidatedOutboxContext
{
    // noinspection PhpTooManyParametersInspection
    public function __construct(
        public string $id,
        public ?string $ownerId,
        public ?string $vendorId,
        public string $fingerprint,
        public \DateTimeImmutable $validatedAt,
        public ?string $rawSha256,
        public string $governanceStatus,
        public ?string $duplicateOfId,
        public ?string $supersededById,
        public ?string $aliasOfId,
        public ?string $conflictWithId,
        public ?string $revalidationDueAt,
        public ?string $revalidationPolicy,
        public string $lastValidationStatus,
        public ?int $lastValidationScore,
        public ?string $evidenceSnapshotId,
        public ?string $providerDigest,
    ) {
    }

    /** @noinspection PhpTooManyParametersInspection */
    public static function fromMutationPlan(
        string $id,
        ?string $ownerId,
        ?string $vendorId,
        string $fingerprint,
        \DateTimeImmutable $validatedAt,
        ?string $evidenceSnapshotId,
        AddressValidatedMutationPlan $plan,
    ): self {
        return new self(
            id: $id,
            ownerId: $ownerId,
            vendorId: $vendorId,
            fingerprint: $fingerprint,
            validatedAt: $validatedAt,
            rawSha256: $plan->rawSha256,
            governanceStatus: $plan->governanceStatus,
            duplicateOfId: $plan->duplicateOfId,
            supersededById: $plan->supersededById,
            aliasOfId: $plan->aliasOfId,
            conflictWithId: $plan->conflictWithId,
            revalidationDueAt: $plan->revalidationDueAt,
            revalidationPolicy: $plan->revalidationPolicy,
            lastValidationStatus: $plan->lastValidationStatus,
            lastValidationScore: $plan->lastValidationScore,
            evidenceSnapshotId: $evidenceSnapshotId,
            providerDigest: $plan->providerDigest,
        );
    }
}
