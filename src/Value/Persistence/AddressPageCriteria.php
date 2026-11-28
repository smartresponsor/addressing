<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Value\Persistence;

/** Represents a tenant-scoped query with immutable scope and copy-on-write pagination and filters. */
final class AddressPageCriteria
{
    /** @var array<string, mixed> */
    private array $filters = [];

    private int $limit = 25;

    private ?string $cursor = null;

    /** @psalm-mutation-free */
    private function __construct(
        private readonly ?string $ownerId,
        private readonly ?string $vendorId,
        private readonly ?string $countryCode,
        private readonly ?string $query,
    ) {
    }

    /** Creates the base criteria from the supplied owner, vendor, country and search constraints. */
    public static function forScope(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $query): self
    {
        return new self($ownerId, $vendorId, $countryCode, $query);
    }

    /** Returns a cloned criteria with the requested page size and cursor, leaving the original unchanged. */
    public function withPagination(int $limit, ?string $cursor): self
    {
        $criteria = clone $this;
        $criteria->limit = $limit;
        $criteria->cursor = $cursor;

        return $criteria;
    }

    /**
     * Returns a cloned criteria carrying the supplied operational and evidence filters.
     *
     * @param array<string, mixed> $filters
     */
    public function withFilters(array $filters): self
    {
        $criteria = clone $this;
        $criteria->filters = $filters;

        return $criteria;
    }

    /** Returns the requested owner scope when provided. */
    public function ownerId(): ?string
    {
        return $this->ownerId;
    }

    /** Returns the requested vendor scope when provided. */
    public function vendorId(): ?string
    {
        return $this->vendorId;
    }

    /** Returns the optional country filter. */
    public function countryCode(): ?string
    {
        return $this->countryCode;
    }

    /** Returns the optional free-text search query. */
    public function query(): ?string
    {
        return $this->query;
    }

    /** Returns the requested maximum number of records. */
    public function limit(): int
    {
        return $this->limit;
    }

    /** Returns the opaque continuation cursor, or null for the first page. */
    public function cursor(): ?string
    {
        return $this->cursor;
    }

    /**
     * Returns the additional persistence filters retained for the query.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->filters;
    }
}
