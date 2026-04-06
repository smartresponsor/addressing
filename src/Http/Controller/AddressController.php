<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Controller;

use App\EntityInterface\Record\AddressInterface;
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

    public function create(Request $request): JsonResponse
    {
        $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
        $addressData = $this->addressApiPayloadFactory->createAddressData($payload);

        $this->addressService->create($addressData);

        return new JsonResponse(['id' => $addressData->id()], 201);
    }

    public function get(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $address = $this->addressService->get($id, $owner_id, $vendor_id);
        if (!$address instanceof \App\EntityInterface\Record\AddressInterface) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse($this->addressViewArrayFactory->toArray($address, null));
    }

    public function markDeleted(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $this->addressService->markDeleted($id, $owner_id, $vendor_id);

        return new JsonResponse(['ok' => true]);
    }

    public function page(Request $request): JsonResponse
    {
        $limit = $this->addressQueryFilterFactory->pageLimit($request);
        $cursor = $this->addressQueryFilterFactory->queryStringOrNull($request, 'cursor');
        [$ownerId, $vendorId] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $country_code = $this->addressQueryFilterFactory->queryCountryCodeOrNull($request);
        $query = $this->addressQueryFilterFactory->queryStringOrNull($request, 'q');
        $expectedNormalizationVersion = $this->addressQueryFilterFactory->queryStringOrNull($request, 'expectedNormalizationVersion');
        $filters = $this->addressQueryFilterFactory->operationalFilters($request, true, true);

        $res = $this->addressService->search($ownerId, $vendorId, $country_code, $query, $limit, $cursor, $filters);

        $items = array_map(fn (AddressInterface $address): array => $this->addressViewArrayFactory->toArray($address, $expectedNormalizationVersion), $res['items']);

        return new JsonResponse([
            'items' => $items,
            'nextCursor' => $res['nextCursor'],
        ]);
    }

    public function queueSummary(Request $request): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $country_code = $this->addressQueryFilterFactory->queryCountryCodeOrNull($request);
        $query = $this->addressQueryFilterFactory->queryStringOrNull($request, 'q');
        $summary = $this->addressService->operationalQueueSummary($ownerId, $vendorId, $country_code, $query, $this->addressQueryFilterFactory->operationalFilters($request, false, true));

        return new JsonResponse($summary);
    }

    public function countryPortfolioSummary(Request $request): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $query = $this->addressQueryFilterFactory->queryStringOrNull($request, 'q');
        $summary = $this->addressService->countryPortfolioSummary($ownerId, $vendorId, $query, $this->addressQueryFilterFactory->operationalFilters($request));

        return new JsonResponse(['items' => $summary]);
    }

    public function sourcePortfolioSummary(Request $request): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $country_code = $this->addressQueryFilterFactory->queryCountryCodeOrNull($request);
        $query = $this->addressQueryFilterFactory->queryStringOrNull($request, 'q');
        $summary = $this->addressService->sourcePortfolioSummary($ownerId, $vendorId, $country_code, $query, $this->addressQueryFilterFactory->portfolioFilters($request, true));

        return new JsonResponse(['items' => $summary]);
    }

    public function validationPortfolioSummary(Request $request): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $country_code = $this->addressQueryFilterFactory->queryCountryCodeOrNull($request);
        $query = $this->addressQueryFilterFactory->queryStringOrNull($request, 'q');
        $summary = $this->addressService->validationPortfolioSummary($ownerId, $vendorId, $country_code, $query, $this->addressQueryFilterFactory->portfolioFilters($request, true, true));

        return new JsonResponse(['items' => $summary]);
    }

    public function normalizationPortfolioSummary(Request $request): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $country_code = $this->addressQueryFilterFactory->queryCountryCodeOrNull($request);
        $query = $this->addressQueryFilterFactory->queryStringOrNull($request, 'q');
        $summary = $this->addressService->normalizationPortfolioSummary($ownerId, $vendorId, $country_code, $query, $this->addressQueryFilterFactory->portfolioFilters($request, true, true, true));

        return new JsonResponse(['items' => $summary]);
    }

    public function governanceClusterSummary(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $summary = $this->addressService->governanceClusterSummary($id, $owner_id, $vendor_id);
        if (0 === $summary['clusterSize']) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse($summary);
    }

    public function patchOperational(Request $request, string $id): JsonResponse
    {
        $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
        [$ownerId, $vendorId] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $patch = $this->addressApiPayloadFactory->operationalPatch($payload);

        try {
            $ok = $this->addressService->patchOperational($id, $owner_id, $vendor_id, $patch);
        } catch (\RuntimeException $exception) {
            return new JsonResponse(['error' => 'invalid_governance_transition', 'message' => $exception->getMessage()], 422);
        }

        if (!$ok) {
            return new JsonResponse(['error' => 'not_found_or_not_patched'], 404);
        }

        $address = $this->addressService->get($id, $owner_id, $vendor_id);
        if (!$address instanceof \App\EntityInterface\Record\AddressInterface) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse($this->addressViewArrayFactory->toArray($address, null));
    }

    public function patchOperationalBatch(Request $request): JsonResponse
    {
        $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
        [$ownerId, $vendorId] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $ids = $this->addressApiPayloadFactory->requireStringList($payload, 'ids');
        $patch = $this->addressApiPayloadFactory->operationalPatch($payload);

        $patched_ids = [];
        $failed = [];
        foreach ($ids as $id) {
            try {
                if ($this->addressService->patchOperational($id, $owner_id, $vendor_id, $patch)) {
                    $patched_ids[] = $id;
                }
            } catch (\RuntimeException $exception) {
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

    public function applyValidated(Request $request, string $id): JsonResponse
    {
        $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
        [$ownerId, $vendorId] = $this->addressQueryFilterFactory->tenantFromQuery($request);
        $addressValidated = $this->addressApiPayloadFactory->createAddressValidated($payload);

        $this->addressValidatedApplierService->apply($id, $addressValidated, $ownerId, $vendorId);

        $address = $this->addressService->get($id, $owner_id, $vendor_id);
        if (!$address instanceof \App\EntityInterface\Record\AddressInterface) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse($this->addressViewArrayFactory->toArray($address, null));
    }

    private function createFromManageDto(AddressManageDto $addressManageDto): string
    {
        $addressData = $this->addressInputFactory->fromManageDto($addressManageDto, [
            'id' => (string) new Ulid(),
            'createdAt' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            'sourceSystem' => 'symfony-manage',
            'sourceType' => 'manual',
            'sourceReference' => 'manage-form',
        ]);
        $this->addressService->create($addressData);

        return $addressData->id();
    }

    /** @return list<array{id: string, line1: string, city: string, countryCode: string, governanceStatus: string, validationStatus: string}> */
    private function previewRows(AddressManageDto $addressManageDto): array
    {
        $ownerId = $this->nullableFormString(['ownerId' => $addressManageDto->ownerId], 'ownerId');
        $vendorId = $this->nullableFormString(['vendorId' => $addressManageDto->vendorId], 'vendorId');
        if (null === $ownerId && null === $vendorId) {
            return [];
        }

        return array_map(
            fn (AddressInterface $address): array => $this->addressViewArrayFactory->previewRow($address),
            $this->addressService->search($ownerId, $vendorId, null, null, 10, null)['items']
        );
    }

    /** @param array<string, mixed> $payload */
    private function nullableFormString(array $payload, string $key): ?string
    {
        if (!array_key_exists($key, $payload) || null === $payload[$key]) {
            return null;
        }
        if (!is_scalar($payload[$key])) {
            throw new \RuntimeException('invalid_'.$key);
        }

        $value = trim((string) $payload[$key]);

        return '' === $value ? null : $value;
    }
}
