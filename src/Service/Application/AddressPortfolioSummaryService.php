<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Application;

use App\RepositoryInterface\Persistence\AddressPortfolioRepositoryInterface;

final readonly class AddressPortfolioSummaryService
{
    public function __construct(private AddressPortfolioRepositoryInterface $portfolioRepository)
    {
    }

    /**
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
        return $this->portfolioRepository->summarizeCountryPortfolio($ownerId, $vendorId, $q, $filters);
    }

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
    public function source(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $query, array $filters = []): array
    {
        return $this->portfolioRepository->summarizeSourcePortfolio($ownerId, $vendorId, $countryCode, $query, $filters);
    }

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
    public function validation(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $query, array $filters = []): array
    {
        return $this->portfolioRepository->summarizeValidationPortfolio($ownerId, $vendorId, $countryCode, $query, $filters);
    }

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
    public function normalization(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $query, array $filters = []): array
    {
        return $this->portfolioRepository->summarizeNormalizationPortfolio($ownerId, $vendorId, $countryCode, $query, $filters);
    }
}
