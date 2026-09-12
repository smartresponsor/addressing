<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Application;

use App\Addressing\EntityInterface\Record\AddressInterface;
use App\Addressing\RepositoryInterface\AddressReadRepositoryInterface;
use App\Addressing\Value\Persistence\AddressPageCriteria;

final readonly class AddressReadService
{
    public function __construct(private AddressReadRepositoryInterface $addressReadRepository)
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
        $addressPageCriteria = AddressPageCriteria::forScope($ownerId, $vendorId, $countryCode, $query)
            ->withPagination($limit, $cursor)
            ->withFilters($filters);

        return $this->addressReadRepository->findPage($addressPageCriteria);
    }

    public function dedupe(?string $dedupeKey): ?AddressInterface
    {
        if (null === $dedupeKey) {
            return null;
        }

        return $this->addressReadRepository->findByDedupeKey($dedupeKey);
    }

    public function get(string $id, ?string $ownerId, ?string $vendorId): ?AddressInterface
    {
        return $this->addressReadRepository->get($id, $ownerId, $vendorId);
    }
}
