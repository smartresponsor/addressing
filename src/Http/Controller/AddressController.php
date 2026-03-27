<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Controller;

use App\Contract\Message\AddressRecordPolicy;
use App\Contract\Message\AddressValidated;
use App\Entity\Record\AddressData;
use App\EntityInterface\Record\AddressInterface;
use App\Repository\Persistence\AddressRepository;
use App\Service\Application\AddressValidatedApplierService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Ulid;

final class AddressController
{
    public function __construct(
        private readonly AddressRepository $repo,
        private readonly AddressValidatedApplierService $validatedApplier,
    ) {
    }

    public static function fromPg(\PDO $pg): self
    {
        return new self(
            new AddressRepository($pg),
            new AddressValidatedApplierService($pg),
        );
    }

    public function manage(Request $request): Response
    {
        $createdId = null;
        if ('POST' === strtoupper($request->getMethod())) {
            $payload = $request->request->all();
            if (isset($payload['line1'], $payload['city'], $payload['countryCode'])) {
                $createdId = $this->createFromFormPayload($payload);
            }
        }

        return new Response($this->renderManagePage($createdId));
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
        $limit = (int) ($req->query->get('limit') ?? 25);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $cursor = self::queryStringOrNull($req, 'cursor');
        $ownerId = self::queryStringOrNull($req, 'ownerId');
        $vendorId = self::queryStringOrNull($req, 'vendorId');
        $countryCode = self::queryStringOrNull($req, 'countryCode');
        $countryCode = null !== $countryCode ? strtoupper($countryCode) : null;
        $q = self::queryStringOrNull($req, 'q');
        $expectedNormalizationVersion = self::queryStringOrNull($req, 'expectedNormalizationVersion');
        $filters = [
            'sourceType' => AddressRecordPolicy::normalizeSourceType(self::queryStringOrNull($req, 'sourceType')),
            'governanceStatus' => null !== self::queryStringOrNull($req, 'governanceStatus')
                ? AddressRecordPolicy::normalizeGovernanceStatus(self::queryStringOrNull($req, 'governanceStatus'))
                : null,
            'revalidationPolicy' => AddressRecordPolicy::normalizeRevalidationPolicy(self::queryStringOrNull($req, 'revalidationPolicy')),
            'hasEvidence' => self::queryBoolOrNull($req, 'hasEvidence'),
            'revalidationDueBefore' => self::queryStringOrNull($req, 'revalidationDueBefore'),
            'queue' => self::queryStringOrNull($req, 'queue'),
            'expectedNormalizationVersion' => $expectedNormalizationVersion,
        ];

        $res = $this->repo->findPage($ownerId, $vendorId, $countryCode, $q, $limit, $cursor, $filters);

        $items = array_map(fn (AddressInterface $address): array => self::toArray($address, $expectedNormalizationVersion), $res['items']);

        return new JsonResponse([
            'items' => $items,
            'nextCursor' => $res['nextCursor'],
        ]);
    }

    public function queueSummary(Request $req): JsonResponse
    {
        $ownerId = self::queryStringOrNull($req, 'ownerId');
        $vendorId = self::queryStringOrNull($req, 'vendorId');
        $countryCode = self::queryStringOrNull($req, 'countryCode');
        $countryCode = null !== $countryCode ? strtoupper($countryCode) : null;
        $q = self::queryStringOrNull($req, 'q');
        $summary = $this->repo->summarizeOperationalQueues($ownerId, $vendorId, $countryCode, $q, [
            'sourceType' => AddressRecordPolicy::normalizeSourceType(self::queryStringOrNull($req, 'sourceType')),
            'governanceStatus' => null !== self::queryStringOrNull($req, 'governanceStatus')
                ? AddressRecordPolicy::normalizeGovernanceStatus(self::queryStringOrNull($req, 'governanceStatus'))
                : null,
            'revalidationPolicy' => AddressRecordPolicy::normalizeRevalidationPolicy(self::queryStringOrNull($req, 'revalidationPolicy')),
            'hasEvidence' => self::queryBoolOrNull($req, 'hasEvidence'),
            'revalidationDueBefore' => self::queryStringOrNull($req, 'revalidationDueBefore'),
            'expectedNormalizationVersion' => self::queryStringOrNull($req, 'expectedNormalizationVersion'),
        ]);

        return new JsonResponse($summary);
    }

    public function countryPortfolioSummary(Request $req): JsonResponse
    {
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $q = self::queryStringOrNull($req, 'q');
        $summary = $this->repo->summarizeCountryPortfolio($ownerId, $vendorId, $q, [
            'sourceType' => AddressRecordPolicy::normalizeSourceType(self::queryStringOrNull($req, 'sourceType')),
            'governanceStatus' => null !== self::queryStringOrNull($req, 'governanceStatus')
                ? AddressRecordPolicy::normalizeGovernanceStatus(self::queryStringOrNull($req, 'governanceStatus'))
                : null,
            'revalidationPolicy' => AddressRecordPolicy::normalizeRevalidationPolicy(self::queryStringOrNull($req, 'revalidationPolicy')),
            'hasEvidence' => self::queryBoolOrNull($req, 'hasEvidence'),
            'revalidationDueBefore' => self::queryStringOrNull($req, 'revalidationDueBefore'),
        ]);

        return new JsonResponse(['items' => $summary]);
    }

    public function sourcePortfolioSummary(Request $req): JsonResponse
    {
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $countryCode = self::queryStringOrNull($req, 'countryCode');
        $countryCode = null !== $countryCode ? strtoupper($countryCode) : null;
        $q = self::queryStringOrNull($req, 'q');
        $summary = $this->repo->summarizeSourcePortfolio($ownerId, $vendorId, $countryCode, $q, [
            'sourceType' => AddressRecordPolicy::normalizeSourceType(self::queryStringOrNull($req, 'sourceType')),
            'sourceSystem' => self::queryStringOrNull($req, 'sourceSystem'),
            'governanceStatus' => null !== self::queryStringOrNull($req, 'governanceStatus')
                ? AddressRecordPolicy::normalizeGovernanceStatus(self::queryStringOrNull($req, 'governanceStatus'))
                : null,
            'revalidationPolicy' => AddressRecordPolicy::normalizeRevalidationPolicy(self::queryStringOrNull($req, 'revalidationPolicy')),
            'hasEvidence' => self::queryBoolOrNull($req, 'hasEvidence'),
            'revalidationDueBefore' => self::queryStringOrNull($req, 'revalidationDueBefore'),
        ]);

        return new JsonResponse(['items' => $summary]);
    }

    public function validationPortfolioSummary(Request $req): JsonResponse
    {
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $countryCode = self::queryStringOrNull($req, 'countryCode');
        $countryCode = null !== $countryCode ? strtoupper($countryCode) : null;
        $q = self::queryStringOrNull($req, 'q');
        $summary = $this->repo->summarizeValidationPortfolio($ownerId, $vendorId, $countryCode, $q, [
            'sourceType' => AddressRecordPolicy::normalizeSourceType(self::queryStringOrNull($req, 'sourceType')),
            'sourceSystem' => self::queryStringOrNull($req, 'sourceSystem'),
            'validationProvider' => self::queryStringOrNull($req, 'validationProvider'),
            'validationStatus' => null !== self::queryStringOrNull($req, 'validationStatus')
                ? AddressRecordPolicy::normalizeValidationStatus(self::queryStringOrNull($req, 'validationStatus'))
                : null,
            'governanceStatus' => null !== self::queryStringOrNull($req, 'governanceStatus')
                ? AddressRecordPolicy::normalizeGovernanceStatus(self::queryStringOrNull($req, 'governanceStatus'))
                : null,
            'revalidationPolicy' => AddressRecordPolicy::normalizeRevalidationPolicy(self::queryStringOrNull($req, 'revalidationPolicy')),
            'hasEvidence' => self::queryBoolOrNull($req, 'hasEvidence'),
            'revalidationDueBefore' => self::queryStringOrNull($req, 'revalidationDueBefore'),
        ]);

        return new JsonResponse(['items' => $summary]);
    }

    public function normalizationPortfolioSummary(Request $req): JsonResponse
    {
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $countryCode = self::queryStringOrNull($req, 'countryCode');
        $countryCode = null !== $countryCode ? strtoupper($countryCode) : null;
        $q = self::queryStringOrNull($req, 'q');
        $summary = $this->repo->summarizeNormalizationPortfolio($ownerId, $vendorId, $countryCode, $q, [
            'sourceType' => AddressRecordPolicy::normalizeSourceType(self::queryStringOrNull($req, 'sourceType')),
            'sourceSystem' => self::queryStringOrNull($req, 'sourceSystem'),
            'validationProvider' => self::queryStringOrNull($req, 'validationProvider'),
            'validationStatus' => null !== self::queryStringOrNull($req, 'validationStatus')
                ? AddressRecordPolicy::normalizeValidationStatus(self::queryStringOrNull($req, 'validationStatus'))
                : null,
            'governanceStatus' => null !== self::queryStringOrNull($req, 'governanceStatus')
                ? AddressRecordPolicy::normalizeGovernanceStatus(self::queryStringOrNull($req, 'governanceStatus'))
                : null,
            'revalidationPolicy' => AddressRecordPolicy::normalizeRevalidationPolicy(self::queryStringOrNull($req, 'revalidationPolicy')),
            'hasEvidence' => self::queryBoolOrNull($req, 'hasEvidence'),
            'revalidationDueBefore' => self::queryStringOrNull($req, 'revalidationDueBefore'),
            'expectedNormalizationVersion' => self::queryStringOrNull($req, 'expectedNormalizationVersion'),
        ]);

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

        try {
            $ok = $this->repo->patchOperational($id, $ownerId, $vendorId, [
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
        $patch = [
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

    /** @param array<string, mixed> $payload */
    private function createFromFormPayload(array $payload): string
    {
        $id = (string) new Ulid();
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');

        $address = new AddressData(
            $id,
            self::nullableFormString($payload, 'ownerId'),
            self::nullableFormString($payload, 'vendorId'),
            self::requiredFormString($payload, 'line1'),
            self::nullableFormString($payload, 'line2'),
            self::requiredFormString($payload, 'city'),
            self::nullableFormString($payload, 'region'),
            self::nullableFormString($payload, 'postalCode'),
            strtoupper(self::requiredFormString($payload, 'countryCode')),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'pending',
            null,
            null,
            null,
            $now,
            null,
            null,
        );
        $this->repo->create($address);

        return $id;
    }

    private function renderManagePage(?string $createdId): string
    {
        $notice = null === $createdId ? '' : '<div class="alert alert-success mt-3">Created address: '.htmlspecialchars($createdId, ENT_QUOTES).'</div>';

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Address manager</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; background: #f8fafc; color: #0f172a; }
    .card { max-width: 48rem; margin: 0 auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 12px; padding: 1.5rem; box-shadow: 0 10px 30px rgba(15,23,42,0.08); }
    .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
    label { display: block; font-weight: 600; margin-bottom: 0.35rem; }
    input { width: 100%; padding: 0.7rem 0.8rem; border: 1px solid #94a3b8; border-radius: 8px; box-sizing: border-box; }
    .btn { display: inline-block; margin-top: 1rem; padding: 0.8rem 1rem; border: 0; border-radius: 8px; background: #0f172a; color: #fff; font-weight: 700; cursor: pointer; }
    .alert { padding: 0.85rem 1rem; border-radius: 8px; background: #dcfce7; color: #166534; }
    @media (max-width: 640px) { .grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <main class="card">
    <h1>Address manager</h1>
    <p>Create an address record with the lightweight management form.</p>
    {$notice}
    <form method="post">
      <div class="grid">
        <div><label for="line1">Line 1</label><input id="line1" name="line1" required></div>
        <div><label for="line2">Line 2</label><input id="line2" name="line2"></div>
        <div><label for="city">City</label><input id="city" name="city" required></div>
        <div><label for="region">Region</label><input id="region" name="region"></div>
        <div><label for="postalCode">Postal code</label><input id="postalCode" name="postalCode"></div>
        <div><label for="countryCode">Country code</label><input id="countryCode" name="countryCode" value="US" required></div>
        <div><label for="ownerId">Owner ID</label><input id="ownerId" name="ownerId"></div>
        <div><label for="vendorId">Vendor ID</label><input id="vendorId" name="vendorId"></div>
      </div>
      <button class="btn" type="submit">Create address</button>
    </form>
  </main>
</body>
</html>
HTML;
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
