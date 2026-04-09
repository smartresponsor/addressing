<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Controller;

use App\EntityInterface\Record\AddressInterface;
use RuntimeException;
use App\Http\Dto\AddressInputFactory;
use App\Http\Dto\AddressManageDto;
use App\Http\Factory\AddressApiPayloadFactory;
use App\Http\Factory\AddressQueryFilterFactory;
use App\Http\Factory\AddressViewArrayFactory;
use App\Http\Form\AddressManageType;
use App\Service\Application\AddressService;
use App\Service\Application\AddressValidatedApplierService;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Ulid;
use Twig\Environment;

final readonly class AddressController
{
    public function __construct(
        private AddressValidatedApplierService $addressValidatedApplierService,
        private AddressService $addressService,
        private FormFactoryInterface $formFactory,
        private Environment $twigEnvironment,
        private AddressInputFactory $addressInputFactory,
        private AddressQueryFilterFactory $addressQueryFilterFactory,
        private AddressViewArrayFactory $addressViewArrayFactory,
        private AddressApiPayloadFactory $addressApiPayloadFactory,
    ) {
    }

    /**
     * Renders the management surface and previews matching rows.
     */
    public function manage(Request $request): Response
    {
        $createdId = null;
        $form = $this->formFactory->create(AddressManageType::class, new AddressManageDto());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dto = $form->getData();
            if ($dto instanceof AddressManageDto) {
                $createdId = $this->createFromManageDto($dto);
            }
        }

        $previewRows = $form->getData() instanceof AddressManageDto
            ? $this->previewRows($form->getData())
            : [];

        return new Response($this->twigEnvironment->render('address/manage.html.twig', [
            'manageForm' => $form->createView(),
            'createdId' => $createdId,
            'previewRows' => $previewRows,
        ]));
    }

    /**
     * Creates a new address record from the submitted payload.
     */
    public function create(Request $request): JsonResponse
    {
        $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
        $address_data = $this->addressApiPayloadFactory->createAddressData($payload);

        $this->addressService->create($address_data);

        return new JsonResponse(['id' => $address_data->id()], 201);
    }

    /**
     * Returns a single address record.
     */
    public function get(Request $request, string $id): JsonResponse
    {
        [$owner_id, $vendor_id] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $address = $this->addressService->get($id, $owner_id, $vendor_id);
        if (!$address instanceof AddressInterface) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse($this->addressViewArrayFactory->toArray($address, null));
    }

    /**
     * Marks an address as deleted.
     */
    public function markDeleted(Request $request, string $id): JsonResponse
    {
        [$owner_id, $vendor_id] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $this->addressService->markDeleted($id, $owner_id, $vendor_id);

        return new JsonResponse(['ok' => true]);
    }

    /**
     * Returns a paged address listing.
     */
    public function page(Request $request): JsonResponse
    {
        $limit = $this->addressQueryFilterFactory->pageLimit($request);
        $cursor = $this->addressQueryFilterFactory->queryStringOrNull($request, 'cursor');
        [$owner_id, $vendor_id] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $countryCode = $this->addressQueryFilterFactory->queryCountryCodeOrNull($request);
        $query = $this->addressQueryFilterFactory->queryStringOrNull($request, 'q');
        $expectedNormalizationVersion = $this->addressQueryFilterFactory->queryStringOrNull($request, 'expectedNormalizationVersion');
        $filters = $this->addressQueryFilterFactory->operationalFilters($request, true, true);

        $result = $this->addressService->search($owner_id, $vendor_id, $countryCode, $query, $limit, $cursor, $filters);

        $items = array_map(fn (AddressInterface $address): array => $this->addressViewArrayFactory->toArray($address, $expectedNormalizationVersion), $result['items']);

        return new JsonResponse([
            'items' => $items,
            'nextCursor' => $result['nextCursor'],
        ]);
    }

    /**
     * Returns queue summary metrics.
     */
    public function queueSummary(Request $request): JsonResponse
    {
        [$owner_id, $vendor_id] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $countryCode = $this->addressQueryFilterFactory->queryCountryCodeOrNull($request);
        $query = $this->addressQueryFilterFactory->queryStringOrNull($request, 'q');
        $summary = $this->addressService->operationalQueueSummary($owner_id, $vendor_id, $countryCode, $query, $this->addressQueryFilterFactory->operationalFilters($request, false, true));

        return new JsonResponse($summary);
    }

    /**
     * Returns the country portfolio summary.
     */
    public function countryPortfolioSummary(Request $request): JsonResponse
    {
        [$owner_id, $vendor_id] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $query = $this->addressQueryFilterFactory->queryStringOrNull($request, 'q');
        $summary = $this->addressService->countryPortfolioSummary($owner_id, $vendor_id, $query, $this->addressQueryFilterFactory->operationalFilters($request));

        return new JsonResponse(['items' => $summary]);
    }

    /**
     * Returns the source portfolio summary.
     */
    public function sourcePortfolioSummary(Request $request): JsonResponse
    {
        [$owner_id, $vendor_id] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $countryCode = $this->addressQueryFilterFactory->queryCountryCodeOrNull($request);
        $query = $this->addressQueryFilterFactory->queryStringOrNull($request, 'q');
        $summary = $this->addressService->sourcePortfolioSummary($owner_id, $vendor_id, $countryCode, $query, $this->addressQueryFilterFactory->portfolioFilters($request, true));

        return new JsonResponse(['items' => $summary]);
    }

    /**
     * Returns the validation portfolio summary.
     */
    public function validationPortfolioSummary(Request $request): JsonResponse
    {
        [$owner_id, $vendor_id] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $countryCode = $this->addressQueryFilterFactory->queryCountryCodeOrNull($request);
        $query = $this->addressQueryFilterFactory->queryStringOrNull($request, 'q');
        $summary = $this->addressService->validationPortfolioSummary($owner_id, $vendor_id, $countryCode, $query, $this->addressQueryFilterFactory->portfolioFilters($request, true, true));

        return new JsonResponse(['items' => $summary]);
    }

    /**
     * Returns the normalization portfolio summary.
     */
    public function normalizationPortfolioSummary(Request $request): JsonResponse
    {
        [$owner_id, $vendor_id] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $countryCode = $this->addressQueryFilterFactory->queryCountryCodeOrNull($request);
        $query = $this->addressQueryFilterFactory->queryStringOrNull($request, 'q');
        $summary = $this->addressService->normalizationPortfolioSummary($owner_id, $vendor_id, $countryCode, $query, $this->addressQueryFilterFactory->portfolioFilters($request, true, true, true));

        return new JsonResponse(['items' => $summary]);
    }

    /**
     * Returns governance cluster details for the requested address.
     */
    public function governanceClusterSummary(Request $request, string $id): JsonResponse
    {
        [$owner_id, $vendor_id] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $summary = $this->addressService->governanceClusterSummary($id, $owner_id, $vendor_id);
        if (0 === $summary['clusterSize']) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse($summary);
    }

    /**
     * Applies an operational patch to a single address.
     */
    public function patchOperational(Request $request, string $id): JsonResponse
    {
        $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
        [$owner_id, $vendor_id] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $patch = $this->addressApiPayloadFactory->operationalPatch($payload);

        try {
            $ok = $this->addressService->patchOperational($id, $owner_id, $vendor_id, $patch);
        } catch (RuntimeException $exception) {
            return new JsonResponse(['error' => 'invalid_governance_transition', 'message' => $exception->getMessage()], 422);
        }

        if (!$ok) {
            return new JsonResponse(['error' => 'not_found_or_not_patched'], 404);
        }

        $address = $this->addressService->get($id, $owner_id, $vendor_id);
        if (!$address instanceof AddressInterface) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse($this->addressViewArrayFactory->toArray($address, null));
    }

    /**
     * Applies an operational patch to multiple addresses.
     */
    public function patchOperationalBatch(Request $request): JsonResponse
    {
        $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
        [$owner_id, $vendor_id] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $ids = $this->addressApiPayloadFactory->requireStringList($payload, 'ids');
        $patch = $this->addressApiPayloadFactory->operationalPatch($payload);

        $patched_ids = [];
        $failed = [];
        foreach ($ids as $id) {
            try {
                if ($this->addressService->patchOperational($id, $owner_id, $vendor_id, $patch)) {
                    $patched_ids[] = $id;
                }
            } catch (RuntimeException $exception) {
                $failed[] = ['id' => $id, 'error' => $exception->getMessage()];
            }
        }

        return new JsonResponse([
            'requestedCount' => count($ids),
            'patchedCount' => count($patched_ids),
            'patchedIds' => $patched_ids,
            'failed' => $failed,
        ]);
    }

    /**
     * Applies a validated payload to the requested address.
     */
    public function applyValidated(Request $request, string $id): JsonResponse
    {
        $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
        [$owner_id, $vendor_id] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $address_validated = $this->addressApiPayloadFactory->createAddressValidated($payload);

        $this->addressValidatedApplierService->apply($id, $address_validated, $owner_id, $vendor_id);

        $address = $this->addressService->get($id, $owner_id, $vendor_id);
        if (!$address instanceof AddressInterface) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse($this->addressViewArrayFactory->toArray($address, null));
    }

    /**
     * Creates a record from the manage DTO and returns its identifier.
     */
    private function createFromManageDto(AddressManageDto $address_manage_dto): string
    {
        $address_data = $this->addressInputFactory->fromManageDto($address_manage_dto, [
            'id' => (string) new Ulid(),
            'createdAt' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            'sourceSystem' => 'symfony-manage',
            'sourceType' => 'manual',
            'sourceReference' => 'manage-form',
        ]);
        $this->addressService->create($address_data);

        return $address_data->id();
    }

    /** @return list<array{id: string, line1: string, city: string, countryCode: string, governanceStatus: string, validationStatus: string}> */
    /**
     * Builds a preview slice for the manage form.
     *
     * @return array<int, array<string, mixed>>
     */
    private function previewRows(AddressManageDto $address_manage_dto): array
    {
        $owner_id = $this->nullableFormString(['ownerId' => $address_manage_dto->ownerId], 'ownerId');
        $vendor_id = $this->nullableFormString(['vendorId' => $address_manage_dto->vendorId], 'vendorId');
        if (null === $owner_id && null === $vendor_id) {
            return [];
        }

        return array_map(
            fn (AddressInterface $address): array => $this->addressViewArrayFactory->previewRow($address),
            $this->addressService->search($owner_id, $vendor_id, null, null, 10, null)['items']
        );
    }

    /** @param array<string, mixed> $payload */
    /**
     * Extracts a nullable form string from the payload.
     *
     * @param array<string, mixed> $payload
     */
    private function nullableFormString(array $payload, string $key): ?string
    {
        if (!array_key_exists($key, $payload) || null === $payload[$key]) {
            return null;
        }
        if (!is_scalar($payload[$key])) {
            throw new RuntimeException('invalid_'.$key);
        }

        $value = trim((string) $payload[$key]);

        return '' === $value ? null : $value;
    }
}
