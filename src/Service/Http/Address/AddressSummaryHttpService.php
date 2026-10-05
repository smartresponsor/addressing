<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Http\Address;

use App\Addressing\Factory\AddressQueryFilterFactory;
use App\Addressing\Responder\AddressResponder;
use App\Addressing\Service\Application\AddressGovernanceSummaryService;
use App\Addressing\Service\Application\AddressPortfolioSummaryService;
use App\Addressing\Service\Application\AddressQueueSummaryService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves operational, portfolio, and governance summary endpoints for Addressing HTTP clients.
 */
final readonly class AddressSummaryHttpService
{
    public function __construct(
        private AddressQueueSummaryService $addressQueueSummaryService,
        private AddressGovernanceSummaryService $addressGovernanceSummaryService,
        private AddressPortfolioSummaryService $addressPortfolioSummaryService,
        private AddressQueryFilterFactory $addressQueryFilterFactory,
        private AddressHttpScopeService $addressHttpScopeService,
        private AddressResponder $addressResponder,
    ) {
    }

    /** Returns scoped operational queue counts for revalidation and governance follow-up. */
    public function queueSummary(Request $request): JsonResponse
    {
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'countryCode' => $countryCode, 'query' => $query] = $this->addressHttpScopeService->requestScope($request, true);
        $summary = $this->addressQueueSummaryService->summarize(
            $ownerId,
            $vendorId,
            $countryCode,
            $query,
            $this->addressQueryFilterFactory->operationalFilters($request, false, true),
        );

        return new JsonResponse($summary);
    }

    /** Returns scoped portfolio totals grouped by country. */
    public function countryPortfolioSummary(Request $request): JsonResponse
    {
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'query' => $query] = $this->addressHttpScopeService->requestScope($request);
        $summary = $this->addressPortfolioSummaryService->country(
            $ownerId,
            $vendorId,
            $query,
            $this->addressQueryFilterFactory->operationalFilters($request),
        );

        return $this->addressResponder->summaryItems($summary);
    }

    /** Returns scoped portfolio totals grouped by source provenance. */
    public function sourcePortfolioSummary(Request $request): JsonResponse
    {
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'countryCode' => $countryCode, 'query' => $query] = $this->addressHttpScopeService->requestScope($request, true);
        $summary = $this->addressPortfolioSummaryService->source(
            $ownerId,
            $vendorId,
            $countryCode,
            $query,
            $this->addressQueryFilterFactory->portfolioFilters($request, true),
        );

        return $this->addressResponder->summaryItems($summary);
    }

    /** Returns scoped portfolio totals grouped by validation provider and status. */
    public function validationPortfolioSummary(Request $request): JsonResponse
    {
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'countryCode' => $countryCode, 'query' => $query] = $this->addressHttpScopeService->requestScope($request, true);
        $summary = $this->addressPortfolioSummaryService->validation(
            $ownerId,
            $vendorId,
            $countryCode,
            $query,
            $this->addressQueryFilterFactory->portfolioFilters($request, true, true),
        );

        return $this->addressResponder->summaryItems($summary);
    }

    /** Returns scoped portfolio totals grouped by normalization version and validation status. */
    public function normalizationPortfolioSummary(Request $request): JsonResponse
    {
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'countryCode' => $countryCode, 'query' => $query] = $this->addressHttpScopeService->requestScope($request, true);
        $summary = $this->addressPortfolioSummaryService->normalization(
            $ownerId,
            $vendorId,
            $countryCode,
            $query,
            $this->addressQueryFilterFactory->portfolioFilters($request, true, true, true),
        );

        return $this->addressResponder->summaryItems($summary);
    }

    /** Returns the scoped governance cluster summary for one address or a not-found response. */
    public function governanceClusterSummary(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressHttpScopeService->tenantScope($request);
        $summary = $this->addressGovernanceSummaryService->summarize($id, $ownerId, $vendorId);
        if (0 === $summary['clusterSize']) {
            return $this->addressResponder->notFound();
        }

        return new JsonResponse($summary);
    }
}
