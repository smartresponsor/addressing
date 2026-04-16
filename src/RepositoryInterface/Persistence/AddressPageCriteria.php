<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\RepositoryInterface\Persistence;

final class AddressPageCriteria
{
    /** @var array<string, mixed> */
    private array $filters = [];

    private int $limit = 25;

    private ?string $cursor = null;

    private function __construct(
        private readonly ?string $ownerId,
        private readonly ?string $vendorId,
        private readonly ?string $countryCode,
        private readonly ?string $query,
    ) {
    }

    public static function forScope(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $query): self
    {
        return new self($ownerId, $vendorId, $countryCode, $query);
    }

    public function withPagination(int $limit, ?string $cursor): self
    {
        $criteria = clone $this;
        $criteria->limit = $limit;
        $criteria->cursor = $cursor;

        return $criteria;
    }

    /** @param array<string, mixed> $filters */
    public function withFilters(array $filters): self
    {
        $criteria = clone $this;
        $criteria->filters = $filters;

        return $criteria;
    }

    public function ownerId(): ?string
    {
        return $this->ownerId;
    }

    public function vendorId(): ?string
    {
        return $this->vendorId;
    }

    public function countryCode(): ?string
    {
        return $this->countryCode;
    }

    public function query(): ?string
    {
        return $this->query;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function cursor(): ?string
    {
        return $this->cursor;
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return $this->filters;
    }
}
