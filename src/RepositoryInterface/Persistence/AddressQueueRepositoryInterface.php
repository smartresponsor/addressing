<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\RepositoryInterface\Persistence;

interface AddressQueueRepositoryInterface
{
    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *   'total':int,
     *   'dueForRevalidation':int,
     *   'evidenceMissing':int,
     *   'uncertainValidation':int,
     *   'conflictReview':int,
     *   'duplicateReview':int,
     *   'staleNormalizationVersion':int
     * }
     */
    public function summarizeOperationalQueues(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        array $filters = [],
    ): array;
}
