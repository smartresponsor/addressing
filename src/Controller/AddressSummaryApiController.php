<?php

declare(strict_types=1);

namespace App\Addressing\Controller;

use App\Addressing\Service\Http\Address\AddressSummaryHttpService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exposes Addressing summary and governance read operations.
 */
#[AsController]
final readonly class AddressSummaryApiController
{
    private const string ID_REQUIREMENT = '[0-9A-HJKMNP-TV-Z]{26}|demo-[0-9]{4}';

    public function __construct(private AddressSummaryHttpService $addressSummaryHttpService)
    {
    }

    #[Route('/api/address/queue-summary', name: 'address_api_queue_summary', methods: ['GET'])]
    public function queueSummary(Request $request): JsonResponse
    {
        return $this->addressSummaryHttpService->queueSummary($request);
    }

    #[Route('/api/address/country-portfolio', name: 'address_api_country_portfolio', methods: ['GET'])]
    public function countryPortfolio(Request $request): JsonResponse
    {
        return $this->addressSummaryHttpService->countryPortfolioSummary($request);
    }

    #[Route('/api/address/source-portfolio', name: 'address_api_source_portfolio', methods: ['GET'])]
    public function sourcePortfolio(Request $request): JsonResponse
    {
        return $this->addressSummaryHttpService->sourcePortfolioSummary($request);
    }

    #[Route('/api/address/validation-portfolio', name: 'address_api_validation_portfolio', methods: ['GET'])]
    public function validationPortfolio(Request $request): JsonResponse
    {
        return $this->addressSummaryHttpService->validationPortfolioSummary($request);
    }

    #[Route('/api/address/normalization-portfolio', name: 'address_api_normalization_portfolio', methods: ['GET'])]
    public function normalizationPortfolio(Request $request): JsonResponse
    {
        return $this->addressSummaryHttpService->normalizationPortfolioSummary($request);
    }

    #[Route('/api/address/{id}/governance-cluster', name: 'address_api_governance_cluster', requirements: ['id' => self::ID_REQUIREMENT], methods: ['GET'])]
    public function governanceCluster(Request $request, string $id): JsonResponse
    {
        return $this->addressSummaryHttpService->governanceClusterSummary($request, $id);
    }
}
