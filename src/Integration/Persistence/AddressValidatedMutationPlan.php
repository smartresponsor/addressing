<?php

declare(strict_types=1);

namespace App\Integration\Persistence;

/**
 * @phpstan-type MutationParams array<string, mixed>
 * @phpstan-type MutationAssignments list<string>
 */
final readonly class AddressValidatedMutationPlan
{
    /**
     * @param MutationAssignments  $updateAssignments
     * @param MutationParams       $params
     * @param array<string, mixed>|null $normalizedSnapshot
     */
    public function __construct(
        public array $updateAssignments,
        public array $params,
        public string $governanceStatus,
        public ?string $duplicateOfId,
        public ?string $supersededById,
        public ?string $aliasOfId,
        public ?string $conflictWithId,
        public ?array $normalizedSnapshot,
        public ?string $providerDigest,
        public string $lastValidationStatus,
        public ?int $lastValidationScore,
        public ?string $revalidationDueAt,
        public ?string $revalidationPolicy,
        public ?string $rawSha256,
    ) {
    }

    public function setClause(): string
    {
        return implode(', ', $this->updateAssignments);
    }
}
