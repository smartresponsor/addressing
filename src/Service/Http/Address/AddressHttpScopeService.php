<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Http\Address;

use App\Addressing\Factory\AddressQueryFilterFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extracts tenant and request-scope filters from Addressing HTTP requests.
 */
final readonly class AddressHttpScopeService
{
    public function __construct(private AddressQueryFilterFactory $addressQueryFilterFactory)
    {
    }

    /**
     * Returns owner and vendor scope values parsed from the request query.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function tenantScope(Request $request): array
    {
        return $this->addressQueryFilterFactory->tenantFromQuery($request);
    }

    /**
     * Returns normalized tenant, country, and free-text scope values for an Addressing request.
     *
     * @return array{ownerId: ?string, vendorId: ?string, countryCode: ?string, query: ?string}
     */
    public function requestScope(Request $request, bool $includeCountryCode = false): array
    {
        [$ownerId, $vendorId] = $this->tenantScope($request);

        return [
            'ownerId' => $ownerId,
            'vendorId' => $vendorId,
            'countryCode' => $includeCountryCode
                ? $this->addressQueryFilterFactory->queryCountryCodeOrNull($request)
                : null,
            'query' => $this->addressQueryFilterFactory->queryStringOrNull($request, 'q'),
        ];
    }
}
