<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Controller;

use App\Contract\Message\AddressRecordPolicy;
use App\Contract\Message\AddressValidated;
use App\Entity\Record\AddressData;
use App\EntityInterface\Record\AddressInterface;
use App\Http\Dto\AddressInputFactory;
use App\Http\Dto\AddressManageDto;
use App\Http\Form\AddressManageType;
use App\Repository\Persistence\AddressRepository;
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
        private AddressRepository $addressRepository,
        private AddressValidatedApplierService $addressValidatedApplierService,
        private AddressService $addressService,
        private FormFactoryInterface $formFactory,
        private Environment $twigEnvironment,
        private AddressInputFactory $addressInputFactory,
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
        $in = $this->json($request);

        $id = (string) new Ulid();
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');

        $addressData = new AddressData(
            $id,
            $this->optStr($in, 'ownerId'),
            $this->optStr($in, 'vendorId'),
            $this->reqStr($in, 'line1'),
            $this->optStr($in, 'line2'),
            $this->reqStr($in, 'city'),
            $this->optStr($in, 'region'),
            $this->optStr($in, 'postalCode'),
            strtoupper($this->reqStr($in, 'countryCode')),
            $this->optStr($in, 'line1Norm'),
            $this->optStr($in, 'cityNorm'),
            $this->optStr($in, 'regionNorm'),
            $this->optStr($in, 'postalCodeNorm'),
            $this->optFloat($in, 'latitude'),
            $this->optFloat($in, 'longitude'),
            $this->optStr($in, 'geohash'),
            AddressRecordPolicy::normalizeValidationStatus($this->optStr($in, 'validationStatus'), 'pending'),
            $this->optStr($in, 'validationProvider'),
            $this->optStr($in, 'validatedAt'),
            $this->optStr($in, 'dedupeKey'),
            $now,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $this->optStr($in, 'sourceSystem'),
            AddressRecordPolicy::normalizeSourceType($this->optStr($in, 'sourceType')),
            $this->optStr($in, 'sourceReference'),
            $this->optStr($in, 'normalizationVersion'),
            $this->optArray($in, 'rawInputSnapshot'),
            $this->optArray($in, 'normalizedSnapshot'),
            $this->optStr($in, 'providerDigest'),
            AddressRecordPolicy::normalizeGovernanceStatus($this->optStr($in, 'governanceStatus') ?? 'canonical'),
            $this->optStr($in, 'duplicateOfId'),
            $this->optStr($in, 'supersededById'),
            $this->optStr($in, 'aliasOfId'),
            $this->optStr($in, 'conflictWithId'),
            $this->optStr($in, 'revalidationDueAt'),
            AddressRecordPolicy::normalizeRevalidationPolicy($this->optStr($in, 'revalidationPolicy')),
            $this->optStr($in, 'lastValidationProvider'),
            AddressRecordPolicy::normalizeLastValidationStatus($this->optStr($in, 'lastValidationStatus')),
            $this->optInt($in, 'lastValidationScore')
        );

        $this->addressRepository->create($addressData);

        return new JsonResponse(['id' => $id], 201);
    }

    public function get(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->tenantFromQuery($request);
        $address = $this->addressRepository->get($id, $ownerId, $vendorId);
        if (!$address instanceof \App\EntityInterface\Record\AddressInterface) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse($this->toArray($address, null));
    }

    public function markDeleted(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->tenantFromQuery($request);
        $this->addressRepository->markDeleted($id, $ownerId, $vendorId);

        return new JsonResponse(['ok' => true]);
    }

    public function page(Request $request): JsonResponse
    {
        $limit = $this->pageLimit($request);
        $cursor = $this->queryStringOrNull($request, 'cursor');
        [$ownerId, $vendorId] = $this->tenantFromQuery($request);
        $countryCode = $this->queryCountryCodeOrNull($request);
        $q = $this->queryStringOrNull($request, 'q');
        $expectedNormalizationVersion = $this->queryStringOrNull($request, 'expectedNormalizationVersion');
        $filters = $this->operationalFilters($request, true, true);

        $res = $this->addressRepository->findPage($ownerId, $vendorId, $countryCode, $q, $limit, $cursor, $filters);

        $items = array_map(fn (AddressInterface $address): array => $this->toArray($address, $expectedNormalizationVersion), $res['items']);

        return new JsonResponse([
            'items' => $items,
            'nextCursor' => $res['nextCursor'],
        ]);
    }

    public function queueSummary(Request $request): JsonResponse
    {
        [$ownerId, $vendorId] = $this->tenantFromQuery($request);
        $countryCode = $this->queryCountryCodeOrNull($request);
        $q = $this->queryStringOrNull($request, 'q');
        $summary = $this->addressRepository->summarizeOperationalQueues($ownerId, $vendorId, $countryCode, $q, $this->operationalFilters($request, false, true));

        return new JsonResponse($summary);
    }

    public function countryPortfolioSummary(Request $request): JsonResponse
    {
        [$ownerId, $vendorId] = $this->tenantFromQuery($request);
        $q = $this->queryStringOrNull($request, 'q');
        $summary = $this->addressRepository->summarizeCountryPortfolio($ownerId, $vendorId, $q, $this->operationalFilters($request));

        return new JsonResponse(['items' => $summary]);
    }

    public function sourcePortfolioSummary(Request $request): JsonResponse
    {
        [$ownerId, $vendorId] = $this->tenantFromQuery($request);
        $countryCode = $this->queryCountryCodeOrNull($request);
        $q = $this->queryStringOrNull($request, 'q');
        $summary = $this->addressRepository->summarizeSourcePortfolio($ownerId, $vendorId, $countryCode, $q, $this->portfolioFilters($request, true));

        return new JsonResponse(['items' => $summary]);
    }

    public function validationPortfolioSummary(Request $request): JsonResponse
    {
        [$ownerId, $vendorId] = $this->tenantFromQuery($request);
        $countryCode = $this->queryCountryCodeOrNull($request);
        $q = $this->queryStringOrNull($request, 'q');
        $summary = $this->addressRepository->summarizeValidationPortfolio($ownerId, $vendorId, $countryCode, $q, $this->portfolioFilters($request, true, true));

        return new JsonResponse(['items' => $summary]);
    }

    public function normalizationPortfolioSummary(Request $request): JsonResponse
    {
        [$ownerId, $vendorId] = $this->tenantFromQuery($request);
        $countryCode = $this->queryCountryCodeOrNull($request);
        $q = $this->queryStringOrNull($request, 'q');
        $summary = $this->addressRepository->summarizeNormalizationPortfolio($ownerId, $vendorId, $countryCode, $q, $this->portfolioFilters($request, true, true, true));

        return new JsonResponse(['items' => $summary]);
    }

    public function governanceClusterSummary(Request $request, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = $this->tenantFromQuery($request);
        $summary = $this->addressRepository->summarizeGovernanceCluster($id, $ownerId, $vendorId);
        if (0 === $summary['clusterSize']) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse($summary);
    }

    public function patchOperational(Request $request, string $id): JsonResponse
    {
        $in = $this->json($request);
        [$ownerId, $vendorId] = $this->tenantFromQuery($request);
        $patch = $this->operationalPatch($in);

        try {
            $ok = $this->addressRepository->patchOperational($id, $ownerId, $vendorId, $patch);
        } catch (\RuntimeException $exception) {
            return new JsonResponse(['error' => 'invalid_governance_transition', 'message' => $exception->getMessage()], 422);
        }

        if (!$ok) {
            return new JsonResponse(['error' => 'not_found_or_not_patched'], 404);
        }

        $address = $this->addressRepository->get($id, $ownerId, $vendorId);
        if (!$address instanceof \App\EntityInterface\Record\AddressInterface) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse($this->toArray($address, null));
    }

    public function patchOperationalBatch(Request $request): JsonResponse
    {
        $in = $this->json($request);
        [$ownerId, $vendorId] = $this->tenantFromQuery($request);
        $ids = $this->reqStringList($in, 'ids');
        $patch = $this->operationalPatch($in);

        $patchedIds = [];
        $failed = [];
        foreach ($ids as $id) {
            try {
                if ($this->addressRepository->patchOperational($id, $ownerId, $vendorId, $patch)) {
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
        $in = $this->json($request);
        [$ownerId, $vendorId] = $this->tenantFromQuery($request);

        $addressValidated = AddressValidated::fromArray([
            'line1Norm' => $this->optStr($in, 'line1Norm'),
            'cityNorm' => $this->optStr($in, 'cityNorm'),
            'regionNorm' => $this->optStr($in, 'regionNorm'),
            'postalCodeNorm' => $this->optStr($in, 'postalCodeNorm'),
            'latitude' => $this->optFloat($in, 'latitude'),
            'longitude' => $this->optFloat($in, 'longitude'),
            'geohash' => $this->optStr($in, 'geohash'),
            'validationProvider' => $this->optStr($in, 'provider') ?? $this->optStr($in, 'validationProvider'),
            'validatedAt' => $this->optStr($in, 'validatedAt'),
            'dedupeKey' => $this->optStr($in, 'dedupeKey'),
            'sourceSystem' => $this->optStr($in, 'sourceSystem'),
            'sourceType' => $this->optStr($in, 'sourceType'),
            'sourceReference' => $this->optStr($in, 'sourceReference'),
            'normalizationVersion' => $this->optStr($in, 'normalizationVersion'),
            'rawInput' => $this->optArray($in, 'rawInput'),
            'normalizedSnapshot' => $this->optArray($in, 'normalizedSnapshot'),
            'providerDigest' => $this->optStr($in, 'providerDigest'),
            'governanceStatus' => $this->optStr($in, 'governanceStatus'),
            'duplicateOfId' => $this->optStr($in, 'duplicateOfId'),
            'supersededById' => $this->optStr($in, 'supersededById'),
            'aliasOfId' => $this->optStr($in, 'aliasOfId'),
            'conflictWithId' => $this->optStr($in, 'conflictWithId'),
            'revalidationDueAt' => $this->optStr($in, 'revalidationDueAt'),
            'revalidationPolicy' => $this->optStr($in, 'revalidationPolicy'),
            'lastValidationProvider' => $this->optStr($in, 'lastValidationProvider'),
            'lastValidationStatus' => $this->optStr($in, 'lastValidationStatus'),
            'lastValidationScore' => $this->optInt($in, 'lastValidationScore'),
        ]);

        $this->addressValidatedApplierService->apply($id, $addressValidated, $ownerId, $vendorId);

        $address = $this->addressRepository->get($id, $ownerId, $vendorId);
        if (!$address instanceof \App\EntityInterface\Record\AddressInterface) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse($this->toArray($address, null));
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
            static fn (AddressInterface $address): array => [
                'id' => $address->id(),
                'line1' => $address->line1(),
                'city' => $address->city(),
                'countryCode' => $address->countryCode(),
                'governanceStatus' => $address->governanceStatus(),
                'validationStatus' => $address->validationStatus(),
            ],
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

    /** @return array<string, mixed> */
    private function json(Request $request): array
    {
        $raw = $request->getContent();
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('invalid_json');
        }

        return $data;
    }

    /** @param array<string, mixed> $in */
    private function reqStr(array $in, string $key): string
    {
        if (!array_key_exists($key, $in) || !is_string($in[$key]) || '' === trim($in[$key])) {
            throw new \RuntimeException('missing_'.$key);
        }

        return trim($in[$key]);
    }

    /** @param array<string, mixed> $in */
    private function optStr(array $in, string $key): ?string
    {
        if (!array_key_exists($key, $in) || null === $in[$key]) {
            return null;
        }
        if (!is_string($in[$key])) {
            throw new \RuntimeException('invalid_'.$key);
        }
        $v = trim($in[$key]);

        return '' === $v ? null : $v;
    }

    /**
     * @param array<string, mixed> $in
     *
     * @return list<string>
     */
    private function reqStringList(array $in, string $key): array
    {
        if (!array_key_exists($key, $in) || !is_array($in[$key])) {
            throw new \RuntimeException('missing_'.$key);
        }

        $values = [];
        foreach ($in[$key] as $item) {
            if (!is_string($item) || '' === trim($item)) {
                throw new \RuntimeException('invalid_'.$key);
            }
            $values[] = trim($item);
        }

        if ([] === $values) {
            throw new \RuntimeException('invalid_'.$key);
        }

        return array_values(array_unique($values));
    }

    /** @return array{0: ?string, 1: ?string} */
    private function tenantFromQuery(Request $request): array
    {
        $ownerId = $this->queryStringOrNull($request, 'ownerId');
        $vendorId = $this->queryStringOrNull($request, 'vendorId');

        return [$ownerId, $vendorId];
    }

    private function pageLimit(Request $request): int
    {
        $limit = (int) ($request->query->get('limit') ?? 25);

        return max(1, min($limit, 200));
    }

    private function queryCountryCodeOrNull(Request $request): ?string
    {
        $countryCode = $this->queryStringOrNull($request, 'countryCode');

        return null !== $countryCode ? strtoupper($countryCode) : null;
    }

    /** @return array<string, mixed> */
    private function operationalFilters(
        Request $request,
        bool $includeQueue = false,
        bool $includeExpectedNormalizationVersion = false,
    ): array {
        $filters = [
            'sourceType' => AddressRecordPolicy::normalizeSourceType($this->queryStringOrNull($request, 'sourceType')),
            'governanceStatus' => $this->normalizedGovernanceStatus($request),
            'revalidationPolicy' => AddressRecordPolicy::normalizeRevalidationPolicy($this->queryStringOrNull($request, 'revalidationPolicy')),
            'hasEvidence' => $this->queryBoolOrNull($request, 'hasEvidence'),
            'revalidationDueBefore' => $this->queryStringOrNull($request, 'revalidationDueBefore'),
        ];

        if ($includeQueue) {
            $filters['queue'] = $this->queryStringOrNull($request, 'queue');
        }

        if ($includeExpectedNormalizationVersion) {
            $filters['expectedNormalizationVersion'] = $this->queryStringOrNull($request, 'expectedNormalizationVersion');
        }

        return $filters;
    }

    /** @return array<string, mixed> */
    private function portfolioFilters(
        Request $request,
        bool $includeSourceSystem = false,
        bool $includeValidation = false,
        bool $includeExpectedNormalizationVersion = false,
    ): array {
        $filters = $this->operationalFilters($request, false, $includeExpectedNormalizationVersion);

        if ($includeSourceSystem) {
            $filters['sourceSystem'] = $this->queryStringOrNull($request, 'sourceSystem');
        }

        if ($includeValidation) {
            $filters['validationProvider'] = $this->queryStringOrNull($request, 'validationProvider');
            $filters['validationStatus'] = $this->normalizedValidationStatus($request);
        }

        return $filters;
    }

    private function normalizedGovernanceStatus(Request $request): ?string
    {
        $governanceStatus = $this->queryStringOrNull($request, 'governanceStatus');

        return null !== $governanceStatus
            ? AddressRecordPolicy::normalizeGovernanceStatus($governanceStatus)
            : null;
    }

    private function normalizedValidationStatus(Request $request): ?string
    {
        $validationStatus = $this->queryStringOrNull($request, 'validationStatus');

        return null !== $validationStatus
            ? AddressRecordPolicy::normalizeValidationStatus($validationStatus)
            : null;
    }

    private function queryStringOrNull(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);

        return is_string($value) && '' !== $value ? $value : null;
    }

    private function queryBoolOrNull(Request $request, string $key): ?bool
    {
        $value = $request->query->get($key);
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower(trim($value))) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $in
     *
     * @return array<string, mixed>|null
     */
    private function optArray(array $in, string $key): ?array
    {
        if (!array_key_exists($key, $in) || null === $in[$key]) {
            return null;
        }
        if (!is_array($in[$key])) {
            throw new \RuntimeException('invalid_'.$key);
        }

        return $in[$key];
    }

    /** @param array<string, mixed> $in */
    private function optInt(array $in, string $key): ?int
    {
        if (!array_key_exists($key, $in) || null === $in[$key] || '' === $in[$key]) {
            return null;
        }
        if (is_int($in[$key])) {
            return $in[$key];
        }
        if (is_string($in[$key]) && is_numeric($in[$key])) {
            return (int) $in[$key];
        }
        throw new \RuntimeException('invalid_'.$key);
    }

    /** @param array<string, mixed> $in */
    private function optFloat(array $in, string $key): ?float
    {
        if (!array_key_exists($key, $in) || null === $in[$key] || '' === $in[$key]) {
            return null;
        }
        if (is_int($in[$key]) || is_float($in[$key])) {
            return (float) $in[$key];
        }
        if (is_string($in[$key]) && is_numeric($in[$key])) {
            return (float) $in[$key];
        }
        throw new \RuntimeException('invalid_'.$key);
    }

    /**
     * @param array<string, mixed> $in
     *
     * @return array{
     *   governanceStatus:?string,
     *   duplicateOfId:?string,
     *   supersededById:?string,
     *   aliasOfId:?string,
     *   conflictWithId:?string,
     *   revalidationDueAt:?string,
     *   revalidationPolicy:?string,
     *   lastValidationProvider:?string,
     *   lastValidationStatus:?string,
     *   lastValidationScore:?int
     * }
     */
    private function operationalPatch(array $in): array
    {
        return [
            'governanceStatus' => $this->optStr($in, 'governanceStatus'),
            'duplicateOfId' => $this->optStr($in, 'duplicateOfId'),
            'supersededById' => $this->optStr($in, 'supersededById'),
            'aliasOfId' => $this->optStr($in, 'aliasOfId'),
            'conflictWithId' => $this->optStr($in, 'conflictWithId'),
            'revalidationDueAt' => $this->optStr($in, 'revalidationDueAt'),
            'revalidationPolicy' => $this->optStr($in, 'revalidationPolicy'),
            'lastValidationProvider' => $this->optStr($in, 'lastValidationProvider'),
            'lastValidationStatus' => $this->optStr($in, 'lastValidationStatus'),
            'lastValidationScore' => $this->optInt($in, 'lastValidationScore'),
        ];
    }

    /** @return array<string, mixed> */
    private function toArray(AddressInterface $address, ?string $expectedNormalizationVersion): array
    {
        $governanceLinkId = $this->primaryGovernanceLinkId($address);
        $hasEvidence = null !== $address->providerDigest()
            || null !== $address->rawInputSnapshot()
            || null !== $address->normalizedSnapshot();
        $isRevalidationDue = null !== $address->revalidationDueAt()
            && false !== strtotime($address->revalidationDueAt())
            && strtotime($address->revalidationDueAt()) <= time();
        $isEvidenceMissing = !$hasEvidence;
        $isValidationUncertain = 'uncertain' === $address->validationStatus() || 'uncertain' === $address->lastValidationStatus();
        $isGovernanceConflict = 'conflict' === $address->governanceStatus();
        $isNormalizationStale = null !== $expectedNormalizationVersion
            && $address->normalizationVersion() !== $expectedNormalizationVersion;
        $reviewReason = $this->reviewReason($isGovernanceConflict, $isValidationUncertain, $isEvidenceMissing, $isRevalidationDue, $isNormalizationStale, $address->governanceStatus());

        return [
            'id' => $address->id(),
            'ownerId' => $address->ownerId(),
            'vendorId' => $address->vendorId(),
            'line1' => $address->line1(),
            'line2' => $address->line2(),
            'city' => $address->city(),
            'region' => $address->region(),
            'postalCode' => $address->postalCode(),
            'countryCode' => $address->countryCode(),
            'line1Norm' => $address->line1Norm(),
            'cityNorm' => $address->cityNorm(),
            'regionNorm' => $address->regionNorm(),
            'postalCodeNorm' => $address->postalCodeNorm(),
            'latitude' => $address->latitude(),
            'longitude' => $address->longitude(),
            'geohash' => $address->geohash(),
            'validationStatus' => $address->validationStatus(),
            'validationProvider' => $address->validationProvider(),
            'validatedAt' => $address->validatedAt(),
            'dedupeKey' => $address->dedupeKey(),
            'sourceSystem' => $address->sourceSystem(),
            'sourceType' => $address->sourceType(),
            'sourceReference' => $address->sourceReference(),
            'normalizationVersion' => $address->normalizationVersion(),
            'rawInputSnapshot' => $address->rawInputSnapshot(),
            'normalizedSnapshot' => $address->normalizedSnapshot(),
            'providerDigest' => $address->providerDigest(),
            'hasEvidence' => $hasEvidence,
            'isEvidenceMissing' => $isEvidenceMissing,
            'isValidationUncertain' => $isValidationUncertain,
            'isGovernanceConflict' => $isGovernanceConflict,
            'isNormalizationStale' => $isNormalizationStale,
            'requiresReview' => null !== $reviewReason,
            'reviewReason' => $reviewReason,
            'governanceStatus' => $address->governanceStatus(),
            'governanceLinkId' => $governanceLinkId,
            'hasGovernanceLink' => null !== $governanceLinkId,
            'duplicateOfId' => $address->duplicateOfId(),
            'supersededById' => $address->supersededById(),
            'aliasOfId' => $address->aliasOfId(),
            'conflictWithId' => $address->conflictWithId(),
            'revalidationDueAt' => $address->revalidationDueAt(),
            'isRevalidationDue' => $isRevalidationDue,
            'revalidationPolicy' => $address->revalidationPolicy(),
            'lastValidationProvider' => $address->lastValidationProvider(),
            'lastValidationStatus' => $address->lastValidationStatus(),
            'lastValidationScore' => $address->lastValidationScore(),
            'createdAt' => $address->createdAt(),
            'updatedAt' => $address->updatedAt(),
            'deletedAt' => $address->deletedAt(),
        ];
    }

    private function reviewReason(
        bool $isGovernanceConflict,
        bool $isValidationUncertain,
        bool $isEvidenceMissing,
        bool $isRevalidationDue,
        bool $isNormalizationStale,
        string $governanceStatus,
    ): ?string {
        if ($isGovernanceConflict) {
            return 'governanceConflict';
        }
        if ('duplicate' === $governanceStatus) {
            return 'duplicateReview';
        }
        if ($isValidationUncertain) {
            return 'uncertainValidation';
        }
        if ($isEvidenceMissing) {
            return 'evidenceMissing';
        }
        if ($isRevalidationDue) {
            return 'dueForRevalidation';
        }
        if ($isNormalizationStale) {
            return 'staleNormalizationVersion';
        }

        return null;
    }

    private function primaryGovernanceLinkId(AddressInterface $address): ?string
    {
        foreach ([$address->duplicateOfId(), $address->supersededById(), $address->aliasOfId(), $address->conflictWithId()] as $candidate) {
            if (null !== $candidate && '' !== $candidate) {
                return $candidate;
            }
        }

        return null;
    }
}
