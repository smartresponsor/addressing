<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Http\Address;

use App\Addressing\Factory\AddressApiPayloadFactory;
use App\Addressing\Responder\AddressResponder;
use App\Addressing\Service\Application\AddressWriteService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles Addressing create and delete commands exposed through HTTP endpoints.
 */
final readonly class AddressWriteHttpService
{
    public function __construct(
        private AddressWriteService $addressWriteService,
        private AddressApiPayloadFactory $addressApiPayloadFactory,
        private AddressHttpScopeService $addressHttpScopeService,
        private AddressResponder $addressResponder,
    ) {
    }

    /** Creates an address from the request payload and returns its identifier. */
    public function create(Request $request): JsonResponse
    {
        try {
            $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
            $addressData = $this->addressApiPayloadFactory->createAddressEntity($payload);
            $this->addressWriteService->create($addressData);
        } catch (\Throwable $exception) {
            return $this->addressResponder->invalidRequest($exception);
        }

        return new JsonResponse(['id' => $addressData->id()], Response::HTTP_CREATED);
    }

    /** Deletes one scoped address and returns the canonical no-content response. */
    public function markDeleted(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressHttpScopeService->tenantScope($request);
        $this->addressWriteService->markDeleted($id, $ownerId, $vendorId);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
