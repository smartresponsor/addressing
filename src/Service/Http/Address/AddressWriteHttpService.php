<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Http\Address;

use App\Http\Factory\AddressApiPayloadFactory;
use App\Service\Application\AddressWriteService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AddressWriteHttpService
{
    public function __construct(
        private AddressWriteService $addressWriteService,
        private AddressApiPayloadFactory $addressApiPayloadFactory,
        private AddressHttpScopeService $addressHttpScopeService,
        private AddressHttpResponderService $addressHttpResponderService,
    ) {
    }

    public function create(Request $request): JsonResponse
    {
        try {
            $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
            $addressData = $this->addressApiPayloadFactory->createAddressEntity($payload);
            $this->addressWriteService->create($addressData);
        } catch (\Throwable $exception) {
            return $this->addressHttpResponderService->invalidRequest($exception);
        }

        return new JsonResponse(['id' => $addressData->id()], Response::HTTP_CREATED);
    }

    public function markDeleted(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressHttpScopeService->tenantScope($request);
        $this->addressWriteService->markDeleted($id, $ownerId, $vendorId);

        return new JsonResponse(['ok' => true]);
    }
}
