<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Application;

use App\RepositoryInterface\Persistence\AddressQueueRepositoryInterface;

final readonly class AddressQueueSummaryService
{
    public function __construct(private AddressQueueRepositoryInterface $queueRepository)
    {
    }

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
    public function summarize(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $query,
        array $filters = [],
    ): array {
        /** @var array{
         *   'total':int,
         *   'dueForRevalidation':int,
         *   'evidenceMissing':int,
         *   'uncertainValidation':int,
         *   'conflictReview':int,
         *   'duplicateReview':int,
         *   'staleNormalizationVersion':int
         * } $summary
         */
        $summary = $this->queueRepository->summarizeOperationalQueues($ownerId, $vendorId, $countryCode, $query, $filters);

        return $summary;
    }
}
