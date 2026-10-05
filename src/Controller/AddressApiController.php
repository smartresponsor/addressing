<?php

declare(strict_types=1);

namespace App\Addressing\Controller;

use App\Addressing\Service\Http\Address\AddressManageHttpService;
use App\Addressing\Service\Http\Address\AddressOperationalHttpService;
use App\Addressing\Service\Http\Address\AddressReadHttpService;
use App\Addressing\Service\Http\Address\AddressWriteHttpService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exposes Addressing-owned HTTP operations through Symfony routing metadata.
 *
 * Business behavior remains in the existing HTTP/application services; this
 * controller is the framework transport boundary used by standalone runtime.
 */
#[AsController]
final readonly class AddressApiController
{
    private const string ID_REQUIREMENT = '[0-9A-HJKMNP-TV-Z]{26}|demo-[0-9]{4}';

    public function __construct(
        private AddressManageHttpService $addressManageHttpService,
        private AddressWriteHttpService $addressWriteHttpService,
        private AddressReadHttpService $addressReadHttpService,
        private AddressOperationalHttpService $addressOperationalHttpService,
    ) {
    }

    /**
     * Renders or submits the Addressing-owned management entrypoint through the dedicated HTTP service.
     */
    #[Route('/address/manage', name: 'address_manage', methods: ['GET', 'POST'])]
    public function manage(Request $request): Response
    {
        return $this->addressManageHttpService->manage($request);
    }

    /**
     * Creates an address from the external API request while delegating mutation rules to the write service.
     */
    #[Route('/api/address', name: 'address_api_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        return $this->addressWriteHttpService->create($request);
    }

    /**
     * Returns the canonical paged address projection for the supplied read criteria.
     */
    #[Route('/api/address/page', name: 'address_api_page', methods: ['GET'])]
    public function page(Request $request): JsonResponse
    {
        return $this->addressReadHttpService->page($request);
    }

    /**
     * Exposes address search through the read service's paged query contract.
     */
    #[Route('/api/address/search', name: 'address_api_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        return $this->addressReadHttpService->page($request);
    }

    /**
     * Applies a batch of Addressing operational-state mutations through the operational service boundary.
     */
    #[Route('/api/address/operational-batch', name: 'address_api_operational_batch', methods: ['POST'])]
    public function operationalBatch(Request $request): JsonResponse
    {
        return $this->addressOperationalHttpService->patchOperationalBatch($request);
    }

    /**
     * Reads one address identified by the canonical Addressing identifier accepted by this runtime.
     */
    #[Route('/api/address/{id}', name: 'address_api_read', requirements: ['id' => self::ID_REQUIREMENT], methods: ['GET'])]
    public function read(Request $request, string $id): JsonResponse
    {
        return $this->addressReadHttpService->get($request, $id);
    }

    /**
     * Marks one address deleted while keeping mutation semantics inside the write service.
     */
    #[Route('/api/address/{id}', name: 'address_api_remove', requirements: ['id' => self::ID_REQUIREMENT], methods: ['DELETE'])]
    public function remove(Request $request, string $id): JsonResponse
    {
        return $this->addressWriteHttpService->markDeleted($request, $id);
    }

    /**
     * Applies an operational-state patch to one address without moving business logic into the controller.
     */
    #[Route('/api/address/{id}', name: 'address_api_operational_patch', requirements: ['id' => self::ID_REQUIREMENT], methods: ['PATCH'])]
    public function operationalPatch(Request $request, string $id): JsonResponse
    {
        return $this->addressOperationalHttpService->patchOperational($request, $id);
    }

    /**
     * Applies a normalized validation result to one address through the Addressing operational boundary.
     */
    #[Route('/api/address/{id}/validated', name: 'address_api_apply_validated', requirements: ['id' => self::ID_REQUIREMENT], methods: ['POST'])]
    public function applyValidated(Request $request, string $id): JsonResponse
    {
        return $this->addressOperationalHttpService->applyValidated($request, $id);
    }
}
