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
use RuntimeException;

/**
 *
 */
final readonly class AddressRepository implements AddressRepositoryInterface
{
    /**
     * @param \PDO $pdo
     */
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param \App\EntityInterface\Address\AddressInterface $address
     * @return void
     */
    public function create(AddressInterface $address): void
    {
        $sql = <<<'SQL'
INSERT INTO address_entity
    (id, owner_id, vendor_id, tag, name, company, phone, email, raw,
     line1, line2, city, region, postal_code, country_code,
     line1_norm, city_norm, region_norm, postal_code_norm,
     latitude, longitude, geohash,
     validation_status, validation_provider, validated_at,
     dedupe_key, created_at, updated_at, deleted_at)
VALUES
    (:id, :owner_id, :vendor_id, :tag, :name, :company, :phone, :email, :raw,
     :line1, :line2, :city, :region, :postal_code, :country_code,
     :line1_norm, :city_norm, :region_norm, :postal_code_norm,
     :latitude, :longitude, :geohash,
     :validation_status, :validation_provider, :validated_at,
     :dedupe_key, :created_at, :updated_at, :deleted_at)
SQL;

        $stmt = $this->prepare($sql);
        $this->bind($stmt, $address);
        $stmt->execute();

        $this->appendOutbox('AddressCreated', [
            'id' => $address->id(),
            'ownerId' => $address->ownerId(),
            'vendorId' => $address->vendorId(),
            'countryCode' => $address->countryCode(),
            'createdAt' => $address->createdAt(),
        ]);
    }

    /**
     * @param \App\EntityInterface\Address\AddressInterface $address
     * @return void
     */
    public function update(AddressInterface $address): void
    {
        $sql = <<<'SQL'
UPDATE address_entity SET
    owner_id=:owner_id, vendor_id=:vendor_id, tag=:tag, name=:name, company=:company, phone=:phone, email=:email,
    raw=:raw,
    line1=:line1, line2=:line2, city=:city, region=:region,
    postal_code=:postal_code, country_code=:country_code,
    line1_norm=:line1_norm, city_norm=:city_norm, region_norm=:region_norm, postal_code_norm=:postal_code_norm,
    latitude=:latitude, longitude=:longitude, geohash=:geohash,
    validation_status=:validation_status, validation_provider=:validation_provider, validated_at=:validated_at,
    dedupe_key=:dedupe_key, updated_at=:updated_at, deleted_at=:deleted_at
WHERE id=:id
SQL;

        $stmt = $this->prepare($sql);
        $this->bind($stmt, $address);
        $stmt->execute();

        $this->appendOutbox('AddressUpdated', [
            'id' => $address->id(),
            'updatedAt' => $address->updatedAt() ?? (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    /**
     * @param string $id
     * @return \App\EntityInterface\Address\AddressInterface|null
     */
    public function get(string $id): ?AddressInterface
    {
        $stmt = $this->prepare('SELECT * FROM address_entity WHERE id=:id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }
        /** @var array<string, mixed> $row */
        return $this->map($row);
    }

    /**
     * @param string $id
     * @return void
     */
    public function delete(string $id): void
    {
        $stmt = $this->prepare('UPDATE address_entity SET deleted_at=now() WHERE id=:id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);

        $this->appendOutbox('AddressDeleted', [
            'id' => $id,
            'deletedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    public function findByDedupeKey(string $dedupeKey): ?AddressInterface
    {
        $dedupeKey = trim($dedupeKey);
        if ($dedupeKey === '') {
            return null;
        }

        $stmt = $this->prepare('SELECT * FROM address_entity WHERE dedupe_key = :dedupe AND deleted_at IS NULL');
        $stmt->execute([':dedupe' => $dedupeKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }
        /** @var array<string, mixed> $row */
        return $this->map($row);
    }

    /**
     * @param string $id
     * @return void
     */
    public function markDeleted(string $id): void
    {
        $this->delete($id);
    }

    /**
     * @return array{items: AddressInterface[], nextCursor: ?string}
     */
    public function findPage(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, int $limit, ?string $cursor): array
    {
        $limit = max(1, min(200, $limit));
        $driverAttr = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driverAttr) ? $driverAttr : '';
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
            $op = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
            $where[] = "lower(line1 || ' ' || city || ' ' || coalesce(postal_code,'')) {$op} lower(:q)";
            $params[':q'] = '%' . $q . '%';
        }
        if ($cursor) {
            $where[] = 'id > :cursor';
            $params[':cursor'] = $cursor;
        }

        $sql = 'SELECT * FROM address_entity WHERE ' . implode(' AND ', $where) . ' ORDER BY id ASC LIMIT :limit';
        $stmt = $this->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        /** @var array<int, array<string, mixed>> $safeRows */
        $safeRows = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row)) {
                    /** @var array<string, mixed> $row */
                    $safeRows[] = $row;
                }
            }
        }
        $items = array_map(fn(array $r): AddressInterface => $this->map($r), $safeRows);

        $nextCursor = null;
        if (count($safeRows) === $limit && $safeRows !== []) {
            $last = end($safeRows);
            if (is_array($last) && isset($last['id'])) {
                $nextCursor = (string)$last['id'];
            }
        }

        return ['items' => $items, 'nextCursor' => $nextCursor];
    }

    /**
     * @param \PDOStatement $stmt
     * @param \App\EntityInterface\Address\AddressInterface $a
     * @return void
     */
    private function bind(PDOStatement $stmt, AddressInterface $a): void
    {
        $rawJson = $this->encodeJsonNullable($a->raw());
        $stmt->bindValue(':id', $a->id());
        $stmt->bindValue(':owner_id', $a->ownerId());
        $stmt->bindValue(':vendor_id', $a->vendorId());
        $stmt->bindValue(':tag', $a->tag());
        $stmt->bindValue(':name', $a->name());
        $stmt->bindValue(':company', $a->company());
        $stmt->bindValue(':phone', $a->phone());
        $stmt->bindValue(':email', $a->email());
        $stmt->bindValue(':raw', $rawJson);
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

    /**
     * @param array<string, mixed> $r
     * @return \App\Entity\Address\AddressData
     */
    private function map(array $r): AddressData
    {
        $validationRaw = $this->decodeJsonNullable($r['validation_raw'] ?? null);
        $validationVerdict = $this->decodeJsonNullable($r['validation_verdict'] ?? null);
        $validationDeliverable = $this->asNullableBool($r['validation_deliverable'] ?? null);
        $validationGranularity = $this->asNullableString($r['validation_granularity'] ?? null);
        $validationQuality = $this->asNullableInt($r['validation_quality'] ?? null);
        $raw = $this->decodeJsonNullable($r['raw'] ?? null);

        return new AddressData(
            $this->asString($r['id'] ?? null, 'id'),
            $this->asNullableString($r['owner_id'] ?? null),
            $this->asNullableString($r['vendor_id'] ?? null),
            $this->asNullableString($r['tag'] ?? null),
            $this->asNullableString($r['name'] ?? null),
            $this->asNullableString($r['company'] ?? null),
            $this->asNullableString($r['phone'] ?? null),
            $this->asNullableString($r['email'] ?? null),
            $raw,
            $this->asString($r['line1'] ?? null, 'line1'),
            $this->asNullableString($r['line2'] ?? null),
            $this->asString($r['city'] ?? null, 'city'),
            $this->asNullableString($r['region'] ?? null),
            $this->asNullableString($r['postal_code'] ?? null),
            $this->asString($r['country_code'] ?? null, 'country_code'),
            $this->asNullableString($r['line1_norm'] ?? null),
            $this->asNullableString($r['city_norm'] ?? null),
            $this->asNullableString($r['region_norm'] ?? null),
            $this->asNullableString($r['postal_code_norm'] ?? null),
            $this->asNullableFloat($r['latitude'] ?? null),
            $this->asNullableFloat($r['longitude'] ?? null),
            $this->asNullableString($r['geohash'] ?? null),
            $this->asString($r['validation_status'] ?? null, 'validation_status'),
            $this->asNullableString($r['validation_provider'] ?? null),
            $this->asNullableString($r['validated_at'] ?? null),
            $this->asNullableString($r['dedupe_key'] ?? null),
            $this->asString($r['created_at'] ?? null, 'created_at'),
            $this->asNullableString($r['updated_at'] ?? null),
            $this->asNullableString($r['deleted_at'] ?? null),
            $this->asNullableString($r['validation_fingerprint'] ?? null),
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
        if (!is_string($v) && !is_int($v) && !is_float($v) && !is_bool($v)) {
            return null;
        }
        $s = (string)$v;
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

    private function encodeJsonNullable(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('json_encode_failed');
        }
        return $encoded;
    }

    private function asString(mixed $value, string $field): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string)$value;
        }
        throw new RuntimeException('invalid_' . $field);
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    private function asNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string)$value;
        }
        return null;
    }

    /**
     * @param mixed $value
     * @return float|null
     */
    private function asNullableFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_float($value) || is_int($value)) {
            return (float)$value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float)$value;
        }
        return null;
    }

    /**
     * @param mixed $value
     * @return int|null
     */
    private function asNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int)$value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int)$value;
        }
        return null;
    }

    /**
     * @param mixed $value
     * @return bool|null
     */
    private function asNullableBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        if (is_string($value) && is_numeric($value)) {
            return ((int)$value) === 1;
        }
        return null;
    }

    /**
     * @param string $name
     * @param array $payload
     * @return void
     */
    private function appendOutbox(string $name, array $payload): void
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            throw new RuntimeException('payload_encode_failed');
        }

        $driverAttr = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driverAttr) ? $driverAttr : '';
        $payloadExpr = $driver === 'pgsql'
            ? ':payload::jsonb'
            : ':payload';

        $stmt = $this->prepare(
            "INSERT INTO address_outbox (event_name, event_version, payload)
         VALUES (:name, :ver, {$payloadExpr})"
        );

        $stmt->execute([
            ':name' => $name,
            ':ver' => 1,
            ':payload' => $payloadJson,
        ]);
    }

    /**
     * @param string $sql
     * @return \PDOStatement
     */
    private function prepare(string $sql): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('prepare_failed');
        }
        return $stmt;
    }
}
