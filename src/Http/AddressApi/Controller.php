<?php
declare(strict_types=1);

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

namespace App\Http\AddressApi;

use App\Entity\Address\AddressData;
use App\EntityInterface\Address\AddressInterface;
use App\Repository\Address\AddressRepository;
use App\Service\Address\AddressValidatedApplier;
use PDO;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Ulid;

final class Controller
{
    public function __construct(
        private PDO $pg,
        private AddressRepository $repo,
        private AddressValidatedApplier $validatedApplier,
    ) {
    }

    public static function fromPg(PDO $pg): self
    {
        return new self($pg, new AddressRepository($pg), new AddressValidatedApplier($pg));
    }

    public function create(Request $req): JsonResponse
    {
        $in = self::json($req);

        $id = (string) new Ulid();
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');

        $a = new AddressData(
            $id,
            self::optStr($in, 'ownerId'),
            self::optStr($in, 'vendorId'),
            self::reqStr($in, 'line1'),
            self::optStr($in, 'line2'),
            self::reqStr($in, 'city'),
            self::optLocalizedMap($in, 'line1Localized'),
            self::optLocalizedMap($in, 'cityLocalized'),
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

        $this->repo->create($a);

        return new JsonResponse(['id' => $id], 201);
    }

    public function get(Request $req, string $id): JsonResponse
    {
        try {
            $a = $this->repo->get($id);
        } catch (RuntimeException) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse(self::toArray($a));
    }

    public function markDeleted(Request $req, string $id): JsonResponse
    {
        $this->repo->markDeleted($id);
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

        $cursor = $req->query->get('cursor');
        $cursor = is_string($cursor) && $cursor !== '' ? $cursor : null;

        $ownerId = $req->query->get('ownerId');
        $ownerId = is_string($ownerId) && $ownerId !== '' ? $ownerId : null;

        $vendorId = $req->query->get('vendorId');
        $vendorId = is_string($vendorId) && $vendorId !== '' ? $vendorId : null;

        $countryCode = $req->query->get('countryCode');
        $countryCode = is_string($countryCode) && $countryCode !== '' ? strtoupper($countryCode) : null;

        $q = $req->query->get('q');
        $q = is_string($q) && $q !== '' ? $q : null;

        $res = $this->repo->findPage($ownerId, $vendorId, $countryCode, $q, $limit, $cursor);

        $items = array_map(fn (AddressInterface $a): array => self::toArray($a), $res['items']);

        return new JsonResponse([
            'items' => $items,
            'nextCursor' => $res['nextCursor'],
        ]);
    }

    public function applyValidated(Request $req, string $id): JsonResponse
    {
        $in = self::json($req);

        $validated = [
            'line1Norm' => self::optStr($in, 'line1Norm'),
            'cityNorm' => self::optStr($in, 'cityNorm'),
            'regionNorm' => self::optStr($in, 'regionNorm'),
            'postalCodeNorm' => self::optStr($in, 'postalCodeNorm'),
            'latitude' => self::optFloat($in, 'latitude'),
            'longitude' => self::optFloat($in, 'longitude'),
            'geohash' => self::optStr($in, 'geohash'),
            'status' => self::optStr($in, 'status') ?? self::optStr($in, 'validationStatus') ?? 'verified',
            'provider' => self::optStr($in, 'provider') ?? self::optStr($in, 'validationProvider'),
        ];

        $this->validatedApplier->apply($id, $validated);

        try {
            $a = $this->repo->get($id);
        } catch (RuntimeException) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse(self::toArray($a));
    }

    private static function json(Request $req): array
    {
        $raw = $req->getContent();
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('invalid_json');
        }
        return $data;
    }

    private static function reqStr(array $in, string $key): string
    {
        if (!array_key_exists($key, $in) || !is_string($in[$key]) || trim($in[$key]) === '') {
            throw new RuntimeException('missing_' . $key);
        }
        return trim($in[$key]);
    }

    private static function optStr(array $in, string $key): ?string
    {
        if (!array_key_exists($key, $in) || $in[$key] === null) {
            return null;
        }
        if (!is_string($in[$key])) {
            throw new RuntimeException('invalid_' . $key);
        }
        $v = trim($in[$key]);
        return $v === '' ? null : $v;
    }

    private static function optFloat(array $in, string $key): ?float
    {
        if (!array_key_exists($key, $in) || $in[$key] === null || $in[$key] === '') {
            return null;
        }
        if (is_int($in[$key]) || is_float($in[$key])) {
            return (float) $in[$key];
        }
        if (is_string($in[$key]) && is_numeric($in[$key])) {
            return (float) $in[$key];
        }
        throw new RuntimeException('invalid_' . $key);
    }

    /**
     * @return array<string, string>|null
     */
    private static function optLocalizedMap(array $in, string $key): ?array
    {
        if (!array_key_exists($key, $in) || $in[$key] === null) {
            return null;
        }
        if (!is_array($in[$key])) {
            throw new RuntimeException('invalid_' . $key);
        }
        $out = [];
        foreach ($in[$key] as $locale => $value) {
            if (!is_string($locale) || !is_string($value)) {
                throw new RuntimeException('invalid_' . $key);
            }
            $value = trim($value);
            if ($value !== '') {
                $out[$locale] = $value;
            }
        }
        return $out === [] ? null : $out;
    }

    private static function toArray(AddressInterface $a): array
    {
        return [
            'id' => $a->id(),
            'ownerId' => $a->ownerId(),
            'vendorId' => $a->vendorId(),
            'line1' => $a->line1(),
            'line2' => $a->line2(),
            'city' => $a->city(),
            'line1Localized' => $a->line1Localized(),
            'cityLocalized' => $a->cityLocalized(),
            'region' => $a->region(),
            'postalCode' => $a->postalCode(),
            'countryCode' => $a->countryCode(),
            'line1Norm' => $a->line1Norm(),
            'cityNorm' => $a->cityNorm(),
            'regionNorm' => $a->regionNorm(),
            'postalCodeNorm' => $a->postalCodeNorm(),
            'latitude' => $a->latitude(),
            'longitude' => $a->longitude(),
            'geohash' => $a->geohash(),
            'validationStatus' => $a->validationStatus(),
            'validationProvider' => $a->validationProvider(),
            'validatedAt' => $a->validatedAt(),
            'dedupeKey' => $a->dedupeKey(),
            'createdAt' => $a->createdAt(),
            'updatedAt' => $a->updatedAt(),
            'deletedAt' => $a->deletedAt(),
        ];
    }
}
