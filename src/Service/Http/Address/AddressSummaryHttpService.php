<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Http\Address;

use App\Http\Factory\AddressQueryFilterFactory;
use App\Service\Application\AddressGovernanceSummaryService;
use App\Service\Application\AddressPortfolioSummaryService;
use App\Service\Application\AddressQueueSummaryService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final readonly class AddressSummaryHttpService
{
    public function __construct(
        private AddressQueueSummaryService $addressQueueSummaryService,
        private AddressGovernanceSummaryService $addressGovernanceSummaryService,
        private AddressPortfolioSummaryService $addressPortfolioSummaryService,
        private AddressQueryFilterFactory $addressQueryFilterFactory,
        private AddressHttpScopeService $addressHttpScopeService,
        private AddressHttpResponderService $addressHttpResponderService,
    ) {
    }

    public function queueSummary(Request $request): JsonResponse
    {
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'countryCode' => $countryCode, 'query' => $query] = $this->addressHttpScopeService->requestScope($request, true);
        $summary = $this->addressQueueSummaryService->summarize(
            $ownerId,
            $vendorId,
            $countryCode,
            $query,
            $this->addressQueryFilterFactory->operationalFilters($request, false, true)
        );

        return new JsonResponse($summary);
    }

    public function countryPortfolioSummary(Request $request): JsonResponse
    {
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'query' => $query] = $this->addressHttpScopeService->requestScope($request);
        $summary = $this->addressPortfolioSummaryService->country(
            $ownerId,
            $vendorId,
            $query,
            $this->addressQueryFilterFactory->operationalFilters($request)
        );

        return $this->addressHttpResponderService->summaryItems($summary);
    }

    public function sourcePortfolioSummary(Request $request): JsonResponse
    {
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'countryCode' => $countryCode, 'query' => $query] = $this->addressHttpScopeService->requestScope($request, true);
        $summary = $this->addressPortfolioSummaryService->source(
            $ownerId,
            $vendorId,
            $countryCode,
            $query,
            $this->addressQueryFilterFactory->portfolioFilters($request, true)
        );

        return $this->addressHttpResponderService->summaryItems($summary);
    }

    public function validationPortfolioSummary(Request $request): JsonResponse
    {
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'countryCode' => $countryCode, 'query' => $query] = $this->addressHttpScopeService->requestScope($request, true);
        $summary = $this->addressPortfolioSummaryService->validation(
            $ownerId,
            $vendorId,
            $countryCode,
            $query,
            $this->addressQueryFilterFactory->portfolioFilters($request, true, true)
        );

        return $this->addressHttpResponderService->summaryItems($summary);
    }

    public function normalizationPortfolioSummary(Request $request): JsonResponse
    {
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'countryCode' => $countryCode, 'query' => $query] = $this->addressHttpScopeService->requestScope($request, true);
        $summary = $this->addressPortfolioSummaryService->normalization(
            $ownerId,
            $vendorId,
            $countryCode,
            $query,
            $this->addressQueryFilterFactory->portfolioFilters($request, true, true, true)
        );

        return $this->addressHttpResponderService->summaryItems($summary);
    }

    public function governanceClusterSummary(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressHttpScopeService->tenantScope($request);
        $summary = $this->addressGovernanceSummaryService->summarize($id, $ownerId, $vendorId);
        if (0 === $summary['clusterSize']) {
            return $this->addressHttpResponderService->notFound();
        }

        return new JsonResponse($summary);
    }
}
