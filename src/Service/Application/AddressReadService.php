<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Application;

use App\EntityInterface\Record\AddressInterface;
use App\RepositoryInterface\Persistence\AddressReadRepositoryInterface;
use App\Value\Persistence\AddressPageCriteria;

final readonly class AddressReadService
{
    public function __construct(private AddressReadRepositoryInterface $readRepository)
    {
    }

    /**
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
        $criteria = AddressPageCriteria::forScope($ownerId, $vendorId, $countryCode, $query)
            ->withPagination($limit, $cursor)
            ->withFilters($filters);

        return $this->readRepository->findPage($criteria);
    }

    public function dedupe(?string $dedupeKey): ?AddressInterface
    {
        if (null === $dedupeKey) {
            return null;
        }

        return $this->readRepository->findByDedupeKey($dedupeKey);
    }

    public function get(string $id, ?string $ownerId, ?string $vendorId): ?AddressInterface
    {
        return $this->readRepository->get($id, $ownerId, $vendorId);
    }
}
