<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\AddressApi;

use App\Contract\Message\Address\AddressValidated;
use App\Entity\Record\Address\AddressData;
use App\EntityInterface\Record\Address\AddressInterface;
use App\Repository\Persistence\Address\AddressRepository;
use App\Service\Application\Address\AddressValidatedApplier;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Ulid;

final class Controller
{
    public function __construct(
        private readonly AddressRepository $repo,
        private readonly AddressValidatedApplier $validatedApplier,
    ) {
    }

    public static function fromPg(\PDO $pg): self
    {
        return new self(new AddressRepository($pg), new AddressValidatedApplier($pg));
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
            self::optStr($in, 'validationStatus') ?? 'pending',
            self::optStr($in, 'validationProvider'),
            self::optStr($in, 'validatedAt'),
            self::optStr($in, 'dedupeKey'),
            $now,
            null,
            null
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

        return new JsonResponse(self::toArray($address));
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

        $res = $this->repo->findPage($ownerId, $vendorId, $countryCode, $q, $limit, $cursor);

        $items = array_map(fn (AddressInterface $address): array => self::toArray($address), $res['items']);

        return new JsonResponse([
            'items' => $items,
            'nextCursor' => $res['nextCursor'],
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
        ]);

        $this->validatedApplier->apply($id, $validated, $ownerId, $vendorId);

        $address = $this->repo->get($id, $ownerId, $vendorId);
        if (null === $address) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse(self::toArray($address));
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

    /**
     * @param array<string, mixed> $in
     */
    private static function reqStr(array $in, string $key): string
    {
        if (!array_key_exists($key, $in) || !is_string($in[$key]) || '' === trim($in[$key])) {
            throw new \RuntimeException('missing_'.$key);
        }

        return trim($in[$key]);
    }

    /**
     * @param array<string, mixed> $in
     */
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
     * @return array{0: ?string, 1: ?string}
     */
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

    /**
     * @param array<string, mixed> $in
     */
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
    private static function toArray(AddressInterface $address): array
    {
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
            'createdAt' => $address->createdAt(),
            'updatedAt' => $address->updatedAt(),
            'deletedAt' => $address->deletedAt(),
        ];
    }
}
