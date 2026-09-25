<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Http\Address;

use App\Addressing\EntityInterface\Record\AddressInterface;
use App\Addressing\Factory\AddressQueryFilterFactory;
use App\Addressing\Factory\AddressViewArrayFactory;
use App\Addressing\Responder\AddressResponder;
use App\Addressing\Service\Application\AddressReadService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final readonly class AddressReadHttpService
{
    public function __construct(
        private AddressReadService $addressReadService,
        private AddressQueryFilterFactory $addressQueryFilterFactory,
        private AddressViewArrayFactory $addressViewArrayFactory,
        private AddressHttpScopeService $addressHttpScopeService,
        private AddressResponder $addressResponder,
    ) {
    }

    public function get(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressHttpScopeService->tenantScope($request);
        $address = $this->addressReadService->get($id, $ownerId, $vendorId);

        return $address instanceof AddressInterface
            ? $this->addressResponder->address($address)
            : $this->addressResponder->notFound();
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
            $result['items'],
        );

        return new JsonResponse([
            'items' => $items,
            'nextCursor' => $result['nextCursor'],
        ]);
    }
}
