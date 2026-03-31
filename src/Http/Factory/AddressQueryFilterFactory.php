<?php

declare(strict_types=1);

namespace App\Http\Factory;

use App\Contract\Message\AddressRecordPolicy;
use Symfony\Component\HttpFoundation\Request;

final readonly class AddressQueryFilterFactory
{
    /** @return array{0: ?string, 1: ?string} */
    public function tenantFromQuery(Request $request): array
    {
        $ownerId = $this->queryStringOrNull($request, 'ownerId');
        $vendorId = $this->queryStringOrNull($request, 'vendorId');

        return [$ownerId, $vendorId];
    }

    public function pageLimit(Request $request): int
    {
        $limit = (int) ($request->query->get('limit') ?? 25);

        return max(1, min($limit, 200));
    }

    public function queryCountryCodeOrNull(Request $request): ?string
    {
        $countryCode = $this->queryStringOrNull($request, 'countryCode');

        return null !== $countryCode ? strtoupper($countryCode) : null;
    }

    public function queryStringOrNull(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);

        return is_string($value) && '' !== $value ? $value : null;
    }

    /** @return array<string, mixed> */
    public function operationalFilters(
        Request $request,
        bool $includeQueue = false,
        bool $includeExpectedNormalizationVersion = false,
    ): array {
        $filters = [
            'sourceType' => AddressRecordPolicy::normalizeSourceType($this->queryStringOrNull($request, 'sourceType')),
            'governanceStatus' => $this->normalizedGovernanceStatus($request),
            'revalidationPolicy' => AddressRecordPolicy::normalizeRevalidationPolicy($this->queryStringOrNull($request, 'revalidationPolicy')),
            'hasEvidence' => $this->queryBoolOrNull($request, 'hasEvidence'),
            'revalidationDueBefore' => $this->queryStringOrNull($request, 'revalidationDueBefore'),
        ];

        if ($includeQueue) {
            $filters['queue'] = $this->queryStringOrNull($request, 'queue');
        }

        if ($includeExpectedNormalizationVersion) {
            $filters['expectedNormalizationVersion'] = $this->queryStringOrNull($request, 'expectedNormalizationVersion');
        }

        return $filters;
    }

    /** @return array<string, mixed> */
    public function portfolioFilters(
        Request $request,
        bool $includeSourceSystem = false,
        bool $includeValidation = false,
        bool $includeExpectedNormalizationVersion = false,
    ): array {
        $filters = $this->operationalFilters($request, false, $includeExpectedNormalizationVersion);

        if ($includeSourceSystem) {
            $filters['sourceSystem'] = $this->queryStringOrNull($request, 'sourceSystem');
        }

        if ($includeValidation) {
            $filters['validationProvider'] = $this->queryStringOrNull($request, 'validationProvider');
            $filters['validationStatus'] = $this->normalizedValidationStatus($request);
        }

        return $filters;
    }

    private function normalizedGovernanceStatus(Request $request): ?string
    {
        $governanceStatus = $this->queryStringOrNull($request, 'governanceStatus');

        return null !== $governanceStatus
            ? AddressRecordPolicy::normalizeGovernanceStatus($governanceStatus)
            : null;
    }

    private function normalizedValidationStatus(Request $request): ?string
    {
        $validationStatus = $this->queryStringOrNull($request, 'validationStatus');

        return null !== $validationStatus
            ? AddressRecordPolicy::normalizeValidationStatus($validationStatus)
            : null;
    }

    private function queryBoolOrNull(Request $request, string $key): ?bool
    {
        $value = $request->query->get($key);
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower(trim($value))) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default => null,
        };
    }
}
