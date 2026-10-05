<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Application;

use App\Addressing\RepositoryInterface\AddressPortfolioRepositoryInterface;

/**
 * Provides application-level portfolio summaries grouped by country, source, validation, and normalization.
 */
final readonly class AddressPortfolioSummaryService
{
    public function __construct(private AddressPortfolioRepositoryInterface $addressPortfolioRepository)
    {
    }

    /**
     * Returns portfolio governance and evidence totals grouped by country.
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
    public function country(?string $ownerId, ?string $vendorId, ?string $q = null, array $filters = []): array
    {
        return $this->addressPortfolioRepository->summarizeCountryPortfolio($ownerId, $vendorId, $q, $filters);
    }

    /**
     * Returns portfolio governance and evidence totals grouped by source provenance.
     *
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
    public function source(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $query, array $filters = []): array
    {
        return $this->addressPortfolioRepository->summarizeSourcePortfolio($ownerId, $vendorId, $countryCode, $query, $filters);
    }

    /**
     * Returns portfolio totals grouped by validation provider and validation status.
     *
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
    public function validation(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $query, array $filters = []): array
    {
        return $this->addressPortfolioRepository->summarizeValidationPortfolio($ownerId, $vendorId, $countryCode, $query, $filters);
    }

    /**
     * Returns portfolio totals grouped by normalization version and validation status.
     *
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
    public function normalization(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $query, array $filters = []): array
    {
        return $this->addressPortfolioRepository->summarizeNormalizationPortfolio($ownerId, $vendorId, $countryCode, $query, $filters);
    }
}
