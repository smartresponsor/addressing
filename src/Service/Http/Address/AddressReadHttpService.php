<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Http\Address;

use App\EntityInterface\Record\AddressInterface;
use App\Http\Factory\AddressQueryFilterFactory;
use App\Http\Factory\AddressViewArrayFactory;
use App\Service\Application\AddressReadService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final readonly class AddressReadHttpService
{
    public function __construct(
        private AddressReadService $addressReadService,
        private AddressQueryFilterFactory $addressQueryFilterFactory,
        private AddressViewArrayFactory $addressViewArrayFactory,
        private AddressHttpScopeService $addressHttpScopeService,
        private AddressHttpResponderService $addressHttpResponderService,
    ) {
    }

    public function get(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressHttpScopeService->tenantScope($request);
        $address = $this->addressReadService->get($id, $ownerId, $vendorId);

        return $address instanceof AddressInterface
            ? $this->addressHttpResponderService->address($address)
            : $this->addressHttpResponderService->notFound();
    }

    public function page(Request $request): JsonResponse
    {
        $limit = $this->addressQueryFilterFactory->pageLimit($request);
        $cursor = $this->addressQueryFilterFactory->queryStringOrNull($request, 'cursor');
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'countryCode' => $countryCode, 'query' => $query] = $this->addressHttpScopeService->requestScope($request, true);
        $expectedNormalizationVersion = $this->addressQueryFilterFactory->queryStringOrNull($request, 'expectedNormalizationVersion');
        $filters = $this->addressQueryFilterFactory->operationalFilters($request, true, true);

        $result = $this->addressReadService->search($ownerId, $vendorId, $countryCode, $query, $limit, $cursor, $filters);
        $items = array_map(
            fn (AddressInterface $address): array => $this->addressViewArrayFactory->toArray($address, $expectedNormalizationVersion),
            $result['items']
        );

        return new JsonResponse([
            'items' => $items,
            'nextCursor' => $result['nextCursor'],
        ]);
    }
}
