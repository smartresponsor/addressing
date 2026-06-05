<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Http\Address;

use App\Http\Factory\AddressQueryFilterFactory;
use Symfony\Component\HttpFoundation\Request;

final readonly class AddressHttpScopeService
{
    public function __construct(private AddressQueryFilterFactory $addressQueryFilterFactory)
    {
    }

    /** @return array{0: ?string, 1: ?string} */
    public function tenantScope(Request $request): array
    {
        return $this->addressQueryFilterFactory->tenantFromQuery($request);
    }

    /**
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
