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

    /**
     * Renders the management surface and previews matching rows.
     *
     * @throws \Throwable
     */
    public function manage(Request $request): Response
    {
        $createdAddressId = null;
        $form = $this->formFactory->create(AddressManageType::class, new AddressManageDto());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dto = $form->getData();
            if ($dto instanceof AddressManageDto) {
                $createdAddressId = $this->createFromManageDto($dto);
            }
        }

        $previewRows = $form->getData() instanceof AddressManageDto
            ? $this->previewRows($form->getData())
            : [];

        return new Response($this->twigEnvironment->render('address/manage.html.twig', [
            'manageForm' => $form->createView(),
            'createdId' => $createdAddressId,
            'previewRows' => $previewRows,
        ]));
    }

    /**
     * Creates a new address record from the submitted payload.
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
            $addressData = $this->addressApiPayloadFactory->createAddressData($payload);
            $this->addressService->create($addressData);
        } catch (\Throwable $exception) {
            return $this->invalidRequestResponse($exception);
        }

        return new JsonResponse(['id' => $addressData->id()], Response::HTTP_CREATED);
    }

    /**
     * Returns a single address record.
     */
    public function get(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->tenantScope($request);
        $address = $this->addressService->get($id, $ownerId, $vendorId);

        return $address instanceof AddressInterface
            ? $this->addressResponse($address)
            : $this->notFoundResponse();
    }

    /**
     * Marks an address as deleted.
     */
    public function markDeleted(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->tenantScope($request);
        $this->addressService->markDeleted($id, $ownerId, $vendorId);

        return new JsonResponse(['ok' => true]);
    }

    /**
     * Returns a paged address listing.
     */
    public function page(Request $request): JsonResponse
    {
        $limit = $this->addressQueryFilterFactory->pageLimit($request);
        $cursor = $this->addressQueryFilterFactory->queryStringOrNull($request, 'cursor');
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'countryCode' => $countryCode, 'query' => $query] = $this->requestScope($request, true);
        $expectedNormalizationVersion = $this->addressQueryFilterFactory->queryStringOrNull($request, 'expectedNormalizationVersion');
        $filters = $this->addressQueryFilterFactory->operationalFilters($request, true, true);

        $result = $this->addressService->search($ownerId, $vendorId, $countryCode, $query, $limit, $cursor, $filters);
        $items = array_map(
            fn (AddressInterface $address): array => $this->addressViewArrayFactory->toArray($address, $expectedNormalizationVersion),
            $result['items']
        );

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
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'countryCode' => $countryCode, 'query' => $query] = $this->requestScope($request, true);
        $summary = $this->addressService->operationalQueueSummary(
            $ownerId,
            $vendorId,
            $countryCode,
            $query,
            $this->addressQueryFilterFactory->operationalFilters($request, false, true)
        );

        return new JsonResponse($summary);
    }

    /**
     * Returns the country portfolio summary.
     */
    public function countryPortfolioSummary(Request $request): JsonResponse
    {
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'query' => $query] = $this->requestScope($request);
        $summary = $this->addressService->countryPortfolioSummary(
            $ownerId,
            $vendorId,
            $query,
            $this->addressQueryFilterFactory->operationalFilters($request)
        );

        return $this->summaryItemsResponse($summary);
    }

    /**
     * Returns the source portfolio summary.
     */
    public function sourcePortfolioSummary(Request $request): JsonResponse
    {
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'countryCode' => $countryCode, 'query' => $query] = $this->requestScope($request, true);
        $summary = $this->addressService->sourcePortfolioSummary(
            $ownerId,
            $vendorId,
            $countryCode,
            $query,
            $this->addressQueryFilterFactory->portfolioFilters($request, true)
        );

        return $this->summaryItemsResponse($summary);
    }

    /**
     * Returns the validation portfolio summary.
     */
    public function validationPortfolioSummary(Request $request): JsonResponse
    {
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'countryCode' => $countryCode, 'query' => $query] = $this->requestScope($request, true);
        $summary = $this->addressService->validationPortfolioSummary(
            $ownerId,
            $vendorId,
            $countryCode,
            $query,
            $this->addressQueryFilterFactory->portfolioFilters($request, true, true)
        );

        return $this->summaryItemsResponse($summary);
    }

    /**
     * Returns the normalization portfolio summary.
     */
    public function normalizationPortfolioSummary(Request $request): JsonResponse
    {
        ['ownerId' => $ownerId, 'vendorId' => $vendorId, 'countryCode' => $countryCode, 'query' => $query] = $this->requestScope($request, true);
        $summary = $this->addressService->normalizationPortfolioSummary(
            $ownerId,
            $vendorId,
            $countryCode,
            $query,
            $this->addressQueryFilterFactory->portfolioFilters($request, true, true, true)
        );

        return $this->summaryItemsResponse($summary);
    }

    /**
     * Returns governance cluster details for the requested address.
     */
    public function governanceClusterSummary(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->tenantScope($request);
        $summary = $this->addressService->governanceClusterSummary($id, $ownerId, $vendorId);
        if (0 === $summary['clusterSize']) {
            return $this->notFoundResponse();
        }

        return new JsonResponse($summary);
    }

    /**
     * Applies an operational patch to a single address.
     */
    public function patchOperational(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->tenantScope($request);

        try {
            $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
            $patch = $this->addressApiPayloadFactory->operationalPatch($payload);
            $ok = $this->addressService->patchOperational($id, $ownerId, $vendorId, $patch);
        } catch (\RuntimeException $exception) {
            return new JsonResponse([
                'error' => 'invalid_operational_patch',
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!$ok) {
            return new JsonResponse(['error' => 'not_found_or_not_patched'], Response::HTTP_NOT_FOUND);
        }

        $address = $this->addressService->get($id, $ownerId, $vendorId);

        return $address instanceof AddressInterface
            ? $this->addressResponse($address)
            : $this->notFoundResponse();
    }

    /**
     * Applies an operational patch to multiple addresses.
     */
    public function patchOperationalBatch(Request $request): JsonResponse
    {
        [$ownerId, $vendorId] = $this->tenantScope($request);

        try {
            $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
            $ids = $this->addressApiPayloadFactory->requireStringList($payload, 'ids');
            $patch = $this->addressApiPayloadFactory->operationalPatch($payload);
        } catch (\RuntimeException $exception) {
            return $this->invalidRequestResponse($exception, 'invalid_batch_payload', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $patchedIds = [];
        $failed = [];
        foreach ($ids as $id) {
            try {
                if ($this->addressService->patchOperational($id, $ownerId, $vendorId, $patch)) {
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

    /**
     * Applies a validated payload to the requested address.
     */
    public function applyValidated(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->tenantScope($request);

        try {
            $payload = $this->addressApiPayloadFactory->decodeJsonRequest($request);
            $addressValidated = $this->addressApiPayloadFactory->createAddressValidated($payload);
            $this->addressValidatedApplierService->apply($id, $addressValidated, $ownerId, $vendorId);
        } catch (\RuntimeException $exception) {
            return $this->invalidRequestResponse($exception, 'invalid_validated_payload', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $address = $this->addressService->get($id, $ownerId, $vendorId);

        return $address instanceof AddressInterface
            ? $this->addressResponse($address)
            : $this->notFoundResponse();
    }

    /**
     * Creates a record from the manage DTO and returns its identifier.
     */
    private function createFromManageDto(AddressManageDto $addressManageDto): string
    {
        $addressData = $this->addressInputFactory->fromManageDto($addressManageDto, [
            'id' => (string) new Ulid(),
            'createdAt' => $this->currentTimestampLiteral(),
            'sourceSystem' => 'symfony-manage',
            'sourceType' => 'manual',
            'sourceReference' => 'manage-form',
        ]);
        $this->addressService->create($addressData);

        return $addressData->id();
    }

    private function currentTimestampLiteral(): string
    {
        $now = new \DateTimeImmutable('now');

        return $now->format('Y-m-d H:i:sP');
    }

    /**
     * Builds a preview slice for the manage form.
     *
     * @return array<int, array<string, mixed>>
     */
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
            throw new \RuntimeException('invalid_'.$key);
        }

        $value = trim((string) $payload[$key]);

        return '' === $value ? null : $value;
    }

    /** @return array{0: ?string, 1: ?string} */
    private function tenantScope(Request $request): array
    {
        return $this->addressQueryFilterFactory->tenantFromQuery($request);
    }

    /**
     * @return array{ownerId: ?string, vendorId: ?string, countryCode: ?string, query: ?string}
     */
    private function requestScope(Request $request, bool $includeCountryCode = false): array
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

    private function addressResponse(AddressInterface $address): JsonResponse
    {
        return new JsonResponse($this->addressViewArrayFactory->toArray($address, null));
    }

    /** @param array<int|string, mixed> $summary */
    private function summaryItemsResponse(array $summary): JsonResponse
    {
        return new JsonResponse(['items' => $summary]);
    }

    private function notFoundResponse(): JsonResponse
    {
        return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
    }

    private function invalidRequestResponse(
        \Throwable $exception,
        string $error = 'invalid_request',
        int $status = Response::HTTP_BAD_REQUEST,
    ): JsonResponse {
        return new JsonResponse([
            'error' => $error,
            'message' => $exception->getMessage(),
        ], $status);
    }
}
