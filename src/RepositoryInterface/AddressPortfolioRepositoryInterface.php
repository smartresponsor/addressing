<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

/**
 * Produces aggregate portfolio views over scoped addresses for operational governance reporting.
 */
interface AddressPortfolioRepositoryInterface
{
    /**
     * Groups the scoped address portfolio by country and reports governance and evidence totals.
     *
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   countryCode:string,
     *   'total':int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   'evidenceMissing':int,
     *   'dueForRevalidation':int,
     *   'uncertainValidation':int
     * }>
     */
    public function summarizeCountryPortfolio(
        ?string $ownerId,
        ?string $vendorId,
        ?string $q,
        array $filters = [],
    ): array;

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   sourceSystem:string,
     *   sourceType:string,
     *   'total':int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   'evidenceMissing':int,
     *   'dueForRevalidation':int,
     *   'uncertainValidation':int
     * }>
     */
    public function summarizeSourcePortfolio(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        array $filters = [],
    ): array;

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   validationProvider:string,
     *   validationStatus:string,
     *   'total':int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   'evidenceMissing':int,
     *   'dueForRevalidation':int,
     *   'uncertainValidation':int
     * }>
     */
    public function summarizeValidationPortfolio(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        array $filters = [],
    ): array;

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   normalizationVersion:string,
     *   validationStatus:string,
     *   'total':int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   'evidenceMissing':int,
     *   'dueForRevalidation':int,
     *   'uncertainValidation':int,
     *   staleNormalization:int
     * }>
     */
    public function summarizeNormalizationPortfolio(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        array $filters = [],
    ): array;
}
