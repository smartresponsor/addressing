<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 */
declare(strict_types=1);

namespace App\Repository\Address;

use App\Entity\Address\AddressData;
use App\EntityInterface\Address\AddressInterface;
use App\RepositoryInterface\Address\AddressRepositoryInterface;
use DateTimeImmutable;
use PDO;
use PDOStatement;

final class AddressRepository implements AddressRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(AddressInterface $address): void
    {
        $sql = <<<'SQL'
INSERT INTO address_entity
    (id, owner_id, vendor_id, line1, line2, city, region, postal_code, country_code,
     line1_norm, city_norm, region_norm, postal_code_norm,
     latitude, longitude, geohash,
     validation_status, validation_provider, validated_at,
     dedupe_key, created_at, updated_at, deleted_at)
VALUES
    (:id, :owner_id, :vendor_id, :line1, :line2, :city, :region, :postal_code, :country_code,
     :line1_norm, :city_norm, :region_norm, :postal_code_norm,
     :latitude, :longitude, :geohash,
     :validation_status, :validation_provider, :validated_at,
     :dedupe_key, :created_at, :updated_at, :deleted_at)
SQL;

        $stmt = $this->pdo->prepare($sql);
        $this->bind($stmt, $address);
        $stmt->execute();

        $this->appendOutbox('AddressCreated', 1, [
            'id' => $address->id(),
            'ownerId' => $address->ownerId(),
            'vendorId' => $address->vendorId(),
            'countryCode' => $address->countryCode(),
            'createdAt' => $address->createdAt(),
        ]);
    }

    public function update(AddressInterface $address): void
    {
        $sql = <<<'SQL'
UPDATE address_entity SET
    owner_id=:owner_id, vendor_id=:vendor_id, line1=:line1, line2=:line2, city=:city, region=:region,
    postal_code=:postal_code, country_code=:country_code,
    line1_norm=:line1_norm, city_norm=:city_norm, region_norm=:region_norm, postal_code_norm=:postal_code_norm,
    latitude=:latitude, longitude=:longitude, geohash=:geohash,
    validation_status=:validation_status, validation_provider=:validation_provider, validated_at=:validated_at,
    dedupe_key=:dedupe_key, updated_at=:updated_at, deleted_at=:deleted_at
WHERE id=:id
SQL;

        $stmt = $this->pdo->prepare($sql);
        $this->bind($stmt, $address);
        $stmt->execute();

        $this->appendOutbox('AddressUpdated', 1, [
            'id' => $address->id(),
            'updatedAt' => $address->updatedAt() ?? (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    public function get(string $id): ?AddressInterface
    {
        $stmt = $this->pdo->prepare('SELECT * FROM address_entity WHERE id=:id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->map($row) : null;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE address_entity SET deleted_at=now() WHERE id=:id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);

        $this->appendOutbox('AddressDeleted', 1, [
            'id' => $id,
            'deletedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    /**
     * @return array{items: AddressInterface[], nextCursor: ?string}
     */
    public function findPage(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, int $limit, ?string $cursor): array
    {
        $limit = max(1, min(200, $limit));
        $params = [];
        $where = ['deleted_at IS NULL'];

        if ($ownerId) {
            $where[] = 'owner_id = :owner_id';
            $params[':owner_id'] = $ownerId;
        }
        if ($vendorId) {
            $where[] = 'vendor_id = :vendor_id';
            $params[':vendor_id'] = $vendorId;
        }
        if ($countryCode) {
            $where[] = 'country_code = :country_code';
            $params[':country_code'] = $countryCode;
        }
        if ($q) {
            $where[] = "lower(line1 || ' ' || city || ' ' || coalesce(postal_code,'')) ILIKE lower(:q)";
            $params[':q'] = '%' . $q . '%';
        }
        if ($cursor) {
            $where[] = 'id > :cursor';
            $params[':cursor'] = $cursor;
        }

        $sql = 'SELECT * FROM address_entity WHERE ' . implode(' AND ', $where) . ' ORDER BY id ASC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $items = array_map(fn (array $r): AddressInterface => $this->map($r), $rows);

        $nextCursor = null;
        if (count($rows) === $limit && $rows !== []) {
            $last = end($rows);
            if (is_array($last) && isset($last['id'])) {
                $nextCursor = (string) $last['id'];
            }
        }

        return ['items' => $items, 'nextCursor' => $nextCursor];
    }

    private function bind(PDOStatement $stmt, AddressInterface $a): void
    {
        $stmt->bindValue(':id', $a->id());
        $stmt->bindValue(':owner_id', $a->ownerId());
        $stmt->bindValue(':vendor_id', $a->vendorId());
        $stmt->bindValue(':line1', $a->line1());
        $stmt->bindValue(':line2', $a->line2());
        $stmt->bindValue(':city', $a->city());
        $stmt->bindValue(':region', $a->region());
        $stmt->bindValue(':postal_code', $a->postalCode());
        $stmt->bindValue(':country_code', $a->countryCode());
        $stmt->bindValue(':line1_norm', $a->line1Norm());
        $stmt->bindValue(':city_norm', $a->cityNorm());
        $stmt->bindValue(':region_norm', $a->regionNorm());
        $stmt->bindValue(':postal_code_norm', $a->postalCodeNorm());
        $stmt->bindValue(':latitude', $a->latitude());
        $stmt->bindValue(':longitude', $a->longitude());
        $stmt->bindValue(':geohash', $a->geohash());
        $stmt->bindValue(':validation_status', $a->validationStatus());
        $stmt->bindValue(':validation_provider', $a->validationProvider());
        $stmt->bindValue(':validated_at', $a->validatedAt());
        $stmt->bindValue(':dedupe_key', $a->dedupeKey());
        $stmt->bindValue(':created_at', $a->createdAt());
        $stmt->bindValue(':updated_at', $a->updatedAt());
        $stmt->bindValue(':deleted_at', $a->deletedAt());
    }

    private function map(array $r): AddressData
    {
        $validationRaw = $this->decodeJsonNullable($r['validation_raw'] ?? null);
        $validationVerdict = $this->decodeJsonNullable($r['validation_verdict'] ?? null);
        $validationDeliverable = null;
        if (array_key_exists('validation_deliverable', $r) && $r['validation_deliverable'] !== null) {
            $validationDeliverable = ((int) $r['validation_deliverable']) === 1;
        }
        $validationGranularity = array_key_exists('validation_granularity', $r) && $r['validation_granularity'] !== null
            ? (string) $r['validation_granularity']
            : null;
        $validationQuality = array_key_exists('validation_quality', $r) && $r['validation_quality'] !== null
            ? (int) $r['validation_quality']
            : null;

        return new AddressData(
            (string) $r['id'],
            $r['owner_id'] !== null ? (string) $r['owner_id'] : null,
            $r['vendor_id'] !== null ? (string) $r['vendor_id'] : null,
            (string) $r['line1'],
            $r['line2'] !== null ? (string) $r['line2'] : null,
            (string) $r['city'],
            $r['region'] !== null ? (string) $r['region'] : null,
            $r['postal_code'] !== null ? (string) $r['postal_code'] : null,
            (string) $r['country_code'],
            $r['line1_norm'] !== null ? (string) $r['line1_norm'] : null,
            $r['city_norm'] !== null ? (string) $r['city_norm'] : null,
            $r['region_norm'] !== null ? (string) $r['region_norm'] : null,
            $r['postal_code_norm'] !== null ? (string) $r['postal_code_norm'] : null,
            array_key_exists('latitude', $r) && $r['latitude'] !== null ? (float) $r['latitude'] : null,
            array_key_exists('longitude', $r) && $r['longitude'] !== null ? (float) $r['longitude'] : null,
            $r['geohash'] !== null ? (string) $r['geohash'] : null,
            (string) $r['validation_status'],
            $r['validation_provider'] !== null ? (string) $r['validation_provider'] : null,
            $r['validated_at'] !== null ? (string) $r['validated_at'] : null,
            $r['dedupe_key'] !== null ? (string) $r['dedupe_key'] : null,
            (string) $r['created_at'],
            $r['updated_at'] !== null ? (string) $r['updated_at'] : null,
            $r['deleted_at'] !== null ? (string) $r['deleted_at'] : null,
            array_key_exists('validation_fingerprint', $r) && $r['validation_fingerprint'] !== null ? (string) $r['validation_fingerprint'] : null,
            $validationRaw,
            $validationVerdict,
            $validationDeliverable,
            $validationGranularity,
            $validationQuality
        );
    }

    /** @return array<string, mixed>|null */
    private function decodeJsonNullable(mixed $v): ?array
    {
        if ($v === null) {
            return null;
        }
        if (is_array($v)) {
            /** @var array<string, mixed> $v */
            return $v;
        }
        $s = (string) $v;
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        $decoded = json_decode($s, true);
        if (!is_array($decoded)) {
            return null;
        }
        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function appendOutbox(string $name, int $version, array $payload): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO address_outbox(event_name, event_version, payload) VALUES (:name, :ver, :payload::jsonb)'
        );
        $stmt->execute([
            ':name' => $name,
            ':ver' => $version,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
