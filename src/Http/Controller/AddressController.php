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

final class AddressController
{
    public function __construct(
        private readonly AddressRepository $repo,
        private readonly AddressValidatedApplierService $validatedApplier,
        private readonly AddressService $addressService,
        private readonly FormFactoryInterface $formFactory,
        private readonly Environment $twig,
        private readonly AddressInputFactory $inputFactory,
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

        return new Response($this->twig->render('address/manage.html.twig', [
            'manageForm' => $form->createView(),
            'createdId' => $createdId,
            'previewRows' => $previewRows,
        ]));
    }

    public function create(Request $req): JsonResponse
    {
        $in = self::json($req);

        $id = (string) new Ulid();
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');

        $address = new AddressData(
            $id,
            self::optStr($in, 'ownerId'),
            self::optStr($in, 'vendorId'),
            self::reqStr($in, 'line1'),
            self::optStr($in, 'line2'),
            self::reqStr($in, 'city'),
            self::optStr($in, 'region'),
            self::optStr($in, 'postalCode'),
            strtoupper(self::reqStr($in, 'countryCode')),
            self::optStr($in, 'line1Norm'),
            self::optStr($in, 'cityNorm'),
            self::optStr($in, 'regionNorm'),
            self::optStr($in, 'postalCodeNorm'),
            self::optFloat($in, 'latitude'),
            self::optFloat($in, 'longitude'),
            self::optStr($in, 'geohash'),
            AddressRecordPolicy::normalizeValidationStatus(self::optStr($in, 'validationStatus'), 'pending'),
            self::optStr($in, 'validationProvider'),
            self::optStr($in, 'validatedAt'),
            self::optStr($in, 'dedupeKey'),
            $now,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            self::optStr($in, 'sourceSystem'),
            AddressRecordPolicy::normalizeSourceType(self::optStr($in, 'sourceType')),
            self::optStr($in, 'sourceReference'),
            self::optStr($in, 'normalizationVersion'),
            self::optArray($in, 'rawInputSnapshot'),
            self::optArray($in, 'normalizedSnapshot'),
            self::optStr($in, 'providerDigest'),
            AddressRecordPolicy::normalizeGovernanceStatus(self::optStr($in, 'governanceStatus') ?? 'canonical'),
            self::optStr($in, 'duplicateOfId'),
            self::optStr($in, 'supersededById'),
            self::optStr($in, 'aliasOfId'),
            self::optStr($in, 'conflictWithId'),
            self::optStr($in, 'revalidationDueAt'),
            AddressRecordPolicy::normalizeRevalidationPolicy(self::optStr($in, 'revalidationPolicy')),
            self::optStr($in, 'lastValidationProvider'),
            AddressRecordPolicy::normalizeLastValidationStatus(self::optStr($in, 'lastValidationStatus')),
            self::optInt($in, 'lastValidationScore')
        );

        $this->repo->create($address);

        return new JsonResponse(['id' => $id], 201);
    }

    public function get(Request $req, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $address = $this->repo->get($id, $ownerId, $vendorId);
        if (null === $address) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse(self::toArray($address, null));
    }

    public function markDeleted(Request $req, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $this->repo->markDeleted($id, $ownerId, $vendorId);

        return new JsonResponse(['ok' => true]);
    }

    public function page(Request $req): JsonResponse
    {
        $limit = self::pageLimit($req);
        $cursor = self::queryStringOrNull($req, 'cursor');
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $countryCode = self::queryCountryCodeOrNull($req);
        $q = self::queryStringOrNull($req, 'q');
        $expectedNormalizationVersion = self::queryStringOrNull($req, 'expectedNormalizationVersion');
        $filters = self::operationalFilters($req, true, true);

        $res = $this->repo->findPage($ownerId, $vendorId, $countryCode, $q, $limit, $cursor, $filters);

        $items = array_map(fn (AddressInterface $address): array => self::toArray($address, $expectedNormalizationVersion), $res['items']);

        return new JsonResponse([
            'items' => $items,
            'nextCursor' => $res['nextCursor'],
        ]);
    }

    public function queueSummary(Request $req): JsonResponse
    {
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $countryCode = self::queryCountryCodeOrNull($req);
        $q = self::queryStringOrNull($req, 'q');
        $summary = $this->repo->summarizeOperationalQueues($ownerId, $vendorId, $countryCode, $q, self::operationalFilters($req, false, true));

        return new JsonResponse($summary);
    }

    public function countryPortfolioSummary(Request $req): JsonResponse
    {
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $q = self::queryStringOrNull($req, 'q');
        $summary = $this->repo->summarizeCountryPortfolio($ownerId, $vendorId, $q, self::operationalFilters($req));

        return new JsonResponse(['items' => $summary]);
    }

    public function sourcePortfolioSummary(Request $req): JsonResponse
    {
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $countryCode = self::queryCountryCodeOrNull($req);
        $q = self::queryStringOrNull($req, 'q');
        $summary = $this->repo->summarizeSourcePortfolio($ownerId, $vendorId, $countryCode, $q, self::portfolioFilters($req, true));

        return new JsonResponse(['items' => $summary]);
    }

    public function validationPortfolioSummary(Request $req): JsonResponse
    {
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $countryCode = self::queryCountryCodeOrNull($req);
        $q = self::queryStringOrNull($req, 'q');
        $summary = $this->repo->summarizeValidationPortfolio($ownerId, $vendorId, $countryCode, $q, self::portfolioFilters($req, true, true));

        return new JsonResponse(['items' => $summary]);
    }

    public function normalizationPortfolioSummary(Request $req): JsonResponse
    {
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $countryCode = self::queryCountryCodeOrNull($req);
        $q = self::queryStringOrNull($req, 'q');
        $summary = $this->repo->summarizeNormalizationPortfolio($ownerId, $vendorId, $countryCode, $q, self::portfolioFilters($req, true, true, true));

        return new JsonResponse(['items' => $summary]);
    }

    public function governanceClusterSummary(Request $req, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $summary = $this->repo->summarizeGovernanceCluster($id, $ownerId, $vendorId);
        if (0 === $summary['clusterSize']) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse($summary);
    }

    public function patchOperational(Request $req, string $id): JsonResponse
    {
        $in = self::json($req);
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $patch = self::operationalPatch($in);

        try {
            $ok = $this->repo->patchOperational($id, $ownerId, $vendorId, $patch);
        } catch (\RuntimeException $exception) {
            return new JsonResponse(['error' => 'invalid_governance_transition', 'message' => $exception->getMessage()], 422);
        }

        if (!$ok) {
            return new JsonResponse(['error' => 'not_found_or_not_patched'], 404);
        }

        $address = $this->repo->get($id, $ownerId, $vendorId);
        if (null === $address) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse(self::toArray($address, null));
    }

    public function patchOperationalBatch(Request $req): JsonResponse
    {
        $in = self::json($req);
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $ids = self::reqStringList($in, 'ids');
        $patch = self::operationalPatch($in);

        $patchedIds = [];
        $failed = [];
        foreach ($ids as $id) {
            try {
                if ($this->repo->patchOperational($id, $ownerId, $vendorId, $patch)) {
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

    public function applyValidated(Request $req, string $id): JsonResponse
    {
        $in = self::json($req);
        [$ownerId, $vendorId] = self::tenantFromQuery($req);

        $validated = AddressValidated::fromArray([
            'line1Norm' => self::optStr($in, 'line1Norm'),
            'cityNorm' => self::optStr($in, 'cityNorm'),
            'regionNorm' => self::optStr($in, 'regionNorm'),
            'postalCodeNorm' => self::optStr($in, 'postalCodeNorm'),
            'latitude' => self::optFloat($in, 'latitude'),
            'longitude' => self::optFloat($in, 'longitude'),
            'geohash' => self::optStr($in, 'geohash'),
            'validationProvider' => self::optStr($in, 'provider') ?? self::optStr($in, 'validationProvider'),
            'validatedAt' => self::optStr($in, 'validatedAt'),
            'dedupeKey' => self::optStr($in, 'dedupeKey'),
            'sourceSystem' => self::optStr($in, 'sourceSystem'),
            'sourceType' => self::optStr($in, 'sourceType'),
            'sourceReference' => self::optStr($in, 'sourceReference'),
            'normalizationVersion' => self::optStr($in, 'normalizationVersion'),
            'rawInput' => self::optArray($in, 'rawInput'),
            'normalizedSnapshot' => self::optArray($in, 'normalizedSnapshot'),
            'providerDigest' => self::optStr($in, 'providerDigest'),
            'governanceStatus' => self::optStr($in, 'governanceStatus'),
            'duplicateOfId' => self::optStr($in, 'duplicateOfId'),
            'supersededById' => self::optStr($in, 'supersededById'),
            'aliasOfId' => self::optStr($in, 'aliasOfId'),
            'conflictWithId' => self::optStr($in, 'conflictWithId'),
            'revalidationDueAt' => self::optStr($in, 'revalidationDueAt'),
            'revalidationPolicy' => self::optStr($in, 'revalidationPolicy'),
            'lastValidationProvider' => self::optStr($in, 'lastValidationProvider'),
            'lastValidationStatus' => self::optStr($in, 'lastValidationStatus'),
            'lastValidationScore' => self::optInt($in, 'lastValidationScore'),
        ]);

        $this->validatedApplier->apply($id, $validated, $ownerId, $vendorId);

        $address = $this->repo->get($id, $ownerId, $vendorId);
        if (null === $address) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse(self::toArray($address, null));
    }

    private function createFromManageDto(AddressManageDto $dto): string
    {
        $address = $this->inputFactory->fromManageDto($dto, [
            'id' => (string) new Ulid(),
            'createdAt' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            'sourceSystem' => 'symfony-manage',
            'sourceType' => 'manual',
            'sourceReference' => 'manage-form',
        ]);
        $this->addressService->create($address);

        return $address->id();
    }

    /** @return list<array{id: string, line1: string, city: string, countryCode: string, governanceStatus: string, validationStatus: string}> */
    private function previewRows(AddressManageDto $dto): array
    {
        $ownerId = self::nullableFormString(['ownerId' => $dto->ownerId], 'ownerId');
        $vendorId = self::nullableFormString(['vendorId' => $dto->vendorId], 'vendorId');
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
    private static function requiredFormString(array $payload, string $key): string
    {
        if (!array_key_exists($key, $payload) || !is_scalar($payload[$key])) {
            throw new \RuntimeException('missing_'.$key);
        }

        $value = trim((string) $payload[$key]);
        if ('' === $value) {
            throw new \RuntimeException('missing_'.$key);
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function nullableFormString(array $payload, string $key): ?string
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
    private static function json(Request $req): array
    {
        $raw = $req->getContent();
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('invalid_json');
        }

        return $data;
    }

    /** @param array<string, mixed> $in */
    private static function reqStr(array $in, string $key): string
    {
        if (!array_key_exists($key, $in) || !is_string($in[$key]) || '' === trim($in[$key])) {
            throw new \RuntimeException('missing_'.$key);
        }

        return trim($in[$key]);
    }

    /** @param array<string, mixed> $in */
    private static function optStr(array $in, string $key): ?string
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
    private static function reqStringList(array $in, string $key): array
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
    private static function tenantFromQuery(Request $req): array
    {
        $ownerId = self::queryStringOrNull($req, 'ownerId');
        $vendorId = self::queryStringOrNull($req, 'vendorId');

        return [$ownerId, $vendorId];
    }

    private static function pageLimit(Request $req): int
    {
        $limit = (int) ($req->query->get('limit') ?? 25);

        return max(1, min($limit, 200));
    }

    private static function queryCountryCodeOrNull(Request $req): ?string
    {
        $countryCode = self::queryStringOrNull($req, 'countryCode');

        return null !== $countryCode ? strtoupper($countryCode) : null;
    }

    /** @return array<string, mixed> */
    private static function operationalFilters(
        Request $req,
        bool $includeQueue = false,
        bool $includeExpectedNormalizationVersion = false,
    ): array {
        $filters = [
            'sourceType' => AddressRecordPolicy::normalizeSourceType(self::queryStringOrNull($req, 'sourceType')),
            'governanceStatus' => self::normalizedGovernanceStatus($req),
            'revalidationPolicy' => AddressRecordPolicy::normalizeRevalidationPolicy(self::queryStringOrNull($req, 'revalidationPolicy')),
            'hasEvidence' => self::queryBoolOrNull($req, 'hasEvidence'),
            'revalidationDueBefore' => self::queryStringOrNull($req, 'revalidationDueBefore'),
        ];

        if ($includeQueue) {
            $filters['queue'] = self::queryStringOrNull($req, 'queue');
        }

        if ($includeExpectedNormalizationVersion) {
            $filters['expectedNormalizationVersion'] = self::queryStringOrNull($req, 'expectedNormalizationVersion');
        }

        return $filters;
    }

    /** @return array<string, mixed> */
    private static function portfolioFilters(
        Request $req,
        bool $includeSourceSystem = false,
        bool $includeValidation = false,
        bool $includeExpectedNormalizationVersion = false,
    ): array {
        $filters = self::operationalFilters($req, false, $includeExpectedNormalizationVersion);

        if ($includeSourceSystem) {
            $filters['sourceSystem'] = self::queryStringOrNull($req, 'sourceSystem');
        }

        if ($includeValidation) {
            $filters['validationProvider'] = self::queryStringOrNull($req, 'validationProvider');
            $filters['validationStatus'] = self::normalizedValidationStatus($req);
        }

        return $filters;
    }

    private static function normalizedGovernanceStatus(Request $req): ?string
    {
        $governanceStatus = self::queryStringOrNull($req, 'governanceStatus');

        return null !== $governanceStatus
            ? AddressRecordPolicy::normalizeGovernanceStatus($governanceStatus)
            : null;
    }

    private static function normalizedValidationStatus(Request $req): ?string
    {
        $validationStatus = self::queryStringOrNull($req, 'validationStatus');

        return null !== $validationStatus
            ? AddressRecordPolicy::normalizeValidationStatus($validationStatus)
            : null;
    }

    private static function queryStringOrNull(Request $req, string $key): ?string
    {
        $value = $req->query->get($key);

        return is_string($value) && '' !== $value ? $value : null;
    }

    private static function queryBoolOrNull(Request $req, string $key): ?bool
    {
        $value = $req->query->get($key);
        if (is_bool($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return null;
        }

        return match (strtolower(trim($value))) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default => null,
        };
    }

    private static function optArray(array $in, string $key): ?array
    {
        if (!array_key_exists($key, $in) || null === $in[$key]) {
            return null;
        }
        if (!is_array($in[$key])) {
            throw new \RuntimeException('invalid_'.$key);
        }

        return $in[$key];
    }

    private static function optInt(array $in, string $key): ?int
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

    private static function optFloat(array $in, string $key): ?float
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

    /** @param array<string, mixed> $in */
    private static function operationalPatch(array $in): array
    {
        return [
            'governanceStatus' => self::optStr($in, 'governanceStatus'),
            'duplicateOfId' => self::optStr($in, 'duplicateOfId'),
            'supersededById' => self::optStr($in, 'supersededById'),
            'aliasOfId' => self::optStr($in, 'aliasOfId'),
            'conflictWithId' => self::optStr($in, 'conflictWithId'),
            'revalidationDueAt' => self::optStr($in, 'revalidationDueAt'),
            'revalidationPolicy' => self::optStr($in, 'revalidationPolicy'),
            'lastValidationProvider' => self::optStr($in, 'lastValidationProvider'),
            'lastValidationStatus' => self::optStr($in, 'lastValidationStatus'),
            'lastValidationScore' => self::optInt($in, 'lastValidationScore'),
        ];
    }

    /** @return array<string, mixed> */
    private static function toArray(AddressInterface $address, ?string $expectedNormalizationVersion): array
    {
        $governanceLinkId = self::primaryGovernanceLinkId($address);
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
        $reviewReason = self::reviewReason($isGovernanceConflict, $isValidationUncertain, $isEvidenceMissing, $isRevalidationDue, $isNormalizationStale, $address->governanceStatus());

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

    private static function reviewReason(
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

    private static function primaryGovernanceLinkId(AddressInterface $address): ?string
    {
        foreach ([$address->duplicateOfId(), $address->supersededById(), $address->aliasOfId(), $address->conflictWithId()] as $candidate) {
            if (null !== $candidate && '' !== $candidate) {
                return $candidate;
            }
        }

        return null;
    }
}
