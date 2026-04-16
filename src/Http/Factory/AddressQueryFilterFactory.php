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
            'sourceType' => $this->normalizedSourceType($request),
            'governanceStatus' => $this->normalizedGovernanceStatus($request),
            'revalidationPolicy' => $this->normalizedRevalidationPolicy($request),
            'hasEvidence' => $this->hasEvidenceQuery($request),
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
        return $this->normalizedQueryStringOrNull(
            request: $request,
            key: 'governanceStatus',
            normalizer: static fn (string $value): string => AddressRecordPolicy::normalizeGovernanceStatus($value),
        );
    }

    private function normalizedRevalidationPolicy(Request $request): ?string
    {
        return $this->normalizedQueryStringOrNull(
            request: $request,
            key: 'revalidationPolicy',
            normalizer: static fn (string $value): ?string => AddressRecordPolicy::normalizeRevalidationPolicy($value),
        );
    }

    private function normalizedSourceType(Request $request): ?string
    {
        return $this->normalizedQueryStringOrNull(
            request: $request,
            key: 'sourceType',
            normalizer: static fn (string $value): ?string => AddressRecordPolicy::normalizeSourceType($value),
        );
    }

    private function normalizedValidationStatus(Request $request): ?string
    {
        return $this->normalizedQueryStringOrNull(
            request: $request,
            key: 'validationStatus',
            normalizer: static fn (string $value): string => AddressRecordPolicy::normalizeValidationStatus($value),
        );
    }

    private function hasEvidenceQuery(Request $request): ?bool
    {
        $value = $request->query->get('hasEvidence');
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower(trim($value))) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default => null,
        };
    }

    /** @param callable(string): ?string $normalizer */
    private function normalizedQueryStringOrNull(Request $request, string $key, callable $normalizer): ?string
    {
        $value = $this->queryStringOrNull($request, $key);

        return null === $value ? null : $normalizer($value);
    }
}
