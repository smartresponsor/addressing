<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Application;

use App\Addressing\Contract\AddressInterface;
use App\Addressing\RepositoryInterface\AddressReadRepositoryInterface;
use App\Addressing\Value\Persistence\AddressPageCriteria;

/**
 * Provides scoped application reads, deduplication lookup, and paginated address search.
 */
final readonly class AddressReadService
{
    public function __construct(private AddressReadRepositoryInterface $addressReadRepository)
    {
    }

    /**
     * Searches scoped addresses using normalized filters and cursor pagination criteria.
     *
     * @noinspection PhpTooManyParametersInspection
     *
     * @param array<string, mixed> $filters
     *
     * @return array{'items': list<AddressInterface>, 'nextCursor': ?string}
     */
    public function search(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $query,
        int $limit,
        ?string $cursor,
        array $filters = [],
    ): array {
        $addressPageCriteria = AddressPageCriteria::forScope($ownerId, $vendorId, $countryCode, $query)
            ->withPagination($limit, $cursor)
            ->withFilters($filters);

        return $this->addressReadRepository->findPage($addressPageCriteria);
    }

    /** Resolves the address associated with an exact deduplication key when one is supplied. */
    public function dedupe(?string $dedupeKey): ?AddressInterface
    {
        if (null === $dedupeKey) {
            return null;
        }

        return $this->addressReadRepository->findByDedupeKey($dedupeKey);
    }

    /** Loads one address by identifier when it is visible within the supplied ownership scope. */
    public function get(string $id, ?string $ownerId, ?string $vendorId): ?AddressInterface
    {
        return $this->addressReadRepository->get($id, $ownerId, $vendorId);
    }
}
