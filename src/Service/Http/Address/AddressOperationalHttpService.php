<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Http\Address;

use App\Addressing\EntityInterface\Record\AddressInterface;
use App\Addressing\Factory\AddressApiPayloadFactory;
use App\Addressing\Responder\AddressResponder;
use App\Addressing\Service\Application\AddressOperationalService;
use App\Addressing\Service\Application\AddressReadService;
use App\Addressing\Service\Application\AddressValidatedApplierService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AddressOperationalHttpService
{
    public function __construct(
        private AddressOperationalService $addressOperationalService,
        private AddressReadService $addressReadService,
        private AddressValidatedApplierService $addressValidatedApplierService,
        private AddressApiPayloadFactory $addressApiPayloadFactory,
        private AddressHttpScopeService $addressHttpScopeService,
        private AddressResponder $addressResponder,
    ) {
    }

    public function patchOperational(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressHttpScopeService->tenantScope($request);

        try {
            $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
            $patch = $this->addressApiPayloadFactory->operationalPatch($payload);
            $ok = $this->addressOperationalService->patchOperational($id, $ownerId, $vendorId, $patch);
        } catch (\RuntimeException $exception) {
            return new JsonResponse([
                'error' => 'invalid_operational_patch',
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$ok) {
            return new JsonResponse(['error' => 'not_found_or_not_patched'], Response::HTTP_NOT_FOUND);
        }

        $address = $this->addressReadService->get($id, $ownerId, $vendorId);

        return $address instanceof AddressInterface
            ? $this->addressResponder->address($address)
            : $this->addressResponder->notFound();
    }

    public function patchOperationalBatch(Request $request): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressHttpScopeService->tenantScope($request);

        try {
            $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
            $ids = $this->addressApiPayloadFactory->requireStringList($payload, 'ids');
            $patch = $this->addressApiPayloadFactory->operationalPatch($payload);
        } catch (\RuntimeException $exception) {
            return $this->addressResponder->invalidRequest($exception, 'invalid_batch_payload', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $patchedIds = [];
        $failed = [];
        foreach ($ids as $id) {
            try {
                if ($this->addressOperationalService->patchOperational($id, $ownerId, $vendorId, $patch)) {
                    $patchedIds[] = $id;
                }
            } catch (\RuntimeException $exception) {
                $failed[] = ['id' => $id, 'error' => $exception->getMessage()];
            }
        }

        return new JsonResponse([
            'requestedCount' => count($ids),
            'patchedCount' => count($patchedIds),
            'patchedIds' => $patchedIds,
            'failed' => $failed,
        ]);
    }

    public function applyValidated(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressHttpScopeService->tenantScope($request);

        try {
            $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
            $addressValidated = $this->addressApiPayloadFactory->createAddressValidated($payload);
            $this->addressValidatedApplierService->apply($id, $addressValidated, $ownerId, $vendorId);
        } catch (\RuntimeException $exception) {
            return $this->addressResponder->invalidRequest($exception, 'invalid_validated_payload', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $address = $this->addressReadService->get($id, $ownerId, $vendorId);

        return $address instanceof AddressInterface
            ? $this->addressResponder->address($address)
            : $this->addressResponder->notFound();
    }
}
