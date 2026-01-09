<?php
declare(strict_types=1);
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only.
 * No placeholders or stubs.
 */

namespace App\Repository\Address;

use App\Entity\Address\AddressData;
use App\EntityInterface\Address\AddressInterface;
use App\RepositoryInterface\Address\AddressRepositoryInterface;
use DateTimeImmutable;
use JsonException;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Address repository with transactional outbox.
 *
 * Absolute guarantees:
 * - entity mutation and outbox write are atomic
 * - repository never leaves partial state
 * - JSON payloads are strictly validated
 * - soft-delete is enforced consistently
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
     * @throws \Throwable
     */
    public function create(AddressInterface $address): void
    {
        $this->transactional(function () use ($address): void {
            $stmt = $this->pdo->prepare($this->insertSql());
            $this->bind($stmt, $address);
            $stmt->execute();

            $this->appendOutboxStrict('AddressCreated', [
                'id' => $address->id(),
                'ownerId' => $address->ownerId(),
                'vendorId' => $address->vendorId(),
                'countryCode' => $address->countryCode(),
                'createdAt' => $address->createdAt(),
            ]);
        });
    }

    /**
     * @param \App\EntityInterface\Address\AddressInterface $address
     * @return void
     */
    public function update(AddressInterface $address): void
    {
        $this->transactional(function () use ($address): void {
            $stmt = $this->pdo->prepare($this->updateSql());
            $this->bind($stmt, $address);
            $stmt->execute();

            if ($stmt->rowCount() < 1) {
                throw new RuntimeException('not_found');
            }

            $this->appendOutboxStrict('AddressUpdated', [
                'id' => $address->id(),
                'updatedAt' => $address->updatedAt()
                    ?? (new DateTimeImmutable())->format(DATE_ATOM),
            ]);
        });
    }

    /**
     * @param string $id
     * @return \App\EntityInterface\Address\AddressInterface|null
     */
    public function get(string $id): ?AddressInterface
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM address_entity WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->map($row) : null;
    }

    /**
     * @param string $id
     * @return void
     * @throws \Throwable
     */
    public function delete(string $id): void
    {
        $this->transactional(function () use ($id): void {
            $stmt = $this->pdo->prepare(
                'UPDATE address_entity
                 SET deleted_at = now()
                 WHERE id = :id AND deleted_at IS NULL'
            );
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() < 1) {
                throw new RuntimeException('not_found');
            }

            $this->appendOutboxStrict('AddressDeleted', [
                'id' => $id,
                'deletedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            ]);
        });
    }

    public function findPage(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        int     $limit,
        ?string $cursor
    ): array
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
            $where[] =
                "lower(line1 || ' ' || city || ' ' || coalesce(postal_code,'')) ILIKE lower(:q)";
            $params[':q'] = '%' . $q . '%';
        }
        if ($cursor) {
            $where[] = 'id > :cursor';
            $params[':cursor'] = $cursor;
        }

        $sql =
            'SELECT * FROM address_entity WHERE '
            . implode(' AND ', $where)
            . ' ORDER BY id ASC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $items = array_map(fn(array $r): AddressInterface => $this->map($r), $rows);

        $nextCursor = null;
        if (count($rows) === $limit) {
            $last = end($rows);
            $nextCursor = is_array($last) && isset($last['id'])
                ? (string)$last['id']
                : null;
        }

        return ['items' => $items, 'nextCursor' => $nextCursor];
    }

    /* ========================= Internals ========================= */

    private function transactional(callable $fn): void
    {
        try {
            $this->pdo->beginTransaction();
            $fn();
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param string $name
     * @param array $payload
     * @return void
     */
    private function appendOutboxStrict(string $name, array $payload): void
    {
        try {
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            throw new RuntimeException('outbox_json_encode_failed');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO address_outbox(event_name, event_version, payload)
             VALUES (:name, :ver, :payload::jsonb)'
        );
        $stmt->execute([
            ':name' => $name,
            ':ver' => 1,
            ':payload' => $json,
        ]);
    }

    /**
     * @return string
     */
    private function insertSql(): string
    {
        return <<<'SQL'
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
    }

    /**
     * @return string
     */
    private function updateSql(): string
    {
        return <<<'SQL'
UPDATE address_entity SET
    owner_id=:owner_id, vendor_id=:vendor_id, line1=:line1, line2=:line2, city=:city, region=:region,
    postal_code=:postal_code, country_code=:country_code,
    line1_norm=:line1_norm, city_norm=:city_norm, region_norm=:region_norm, postal_code_norm=:postal_code_norm,
    latitude=:latitude, longitude=:longitude, geohash=:geohash,
    validation_status=:validation_status, validation_provider=:validation_provider, validated_at=:validated_at,
    dedupe_key=:dedupe_key, updated_at=:updated_at, deleted_at=:deleted_at
WHERE id=:id
SQL;
    }

    /**
     * @param \PDOStatement $stmt
     * @param \App\EntityInterface\Address\AddressInterface $a
     * @return void
     */
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

    /**
     * @param array $r
     * @return \App\Entity\Address\AddressData
     */
    private function map(array $r): AddressData
    {
        return new AddressData(
            (string)$r['id'],
            $r['owner_id'] !== null ? (string)$r['owner_id'] : null,
            $r['vendor_id'] !== null ? (string)$r['vendor_id'] : null,
            (string)$r['line1'],
            $r['line2'] !== null ? (string)$r['line2'] : null,
            (string)$r['city'],
            $r['region'] !== null ? (string)$r['region'] : null,
            $r['postal_code'] !== null ? (string)$r['postal_code'] : null,
            (string)$r['country_code'],
            $r['line1_norm'] !== null ? (string)$r['line1_norm'] : null,
            $r['city_norm'] !== null ? (string)$r['city_norm'] : null,
            $r['region_norm'] !== null ? (string)$r['region_norm'] : null,
            $r['postal_code_norm'] !== null ? (string)$r['postal_code_norm'] : null,
            $r['latitude'] !== null ? (float)$r['latitude'] : null,
            $r['longitude'] !== null ? (float)$r['longitude'] : null,
            $r['geohash'] !== null ? (string)$r['geohash'] : null,
            (string)$r['validation_status'],
            $r['validation_provider'] !== null ? (string)$r['validation_provider'] : null,
            $r['validated_at'] !== null ? (string)$r['validated_at'] : null,
            $r['dedupe_key'] !== null ? (string)$r['dedupe_key'] : null,
            (string)$r['created_at'],
            $r['updated_at'] !== null ? (string)$r['updated_at'] : null,
            $r['deleted_at'] !== null ? (string)$r['deleted_at'] : null,
            $r['validation_fingerprint'] ?? null,
            $this->decodeJsonNullable($r['validation_raw'] ?? null),
            $this->decodeJsonNullable($r['validation_verdict'] ?? null),
            isset($r['validation_deliverable']) ? ((int)$r['validation_deliverable']) === 1 : null,
            $r['validation_granularity'] ?? null,
            $r['validation_quality'] ?? null
        );
    }

    /**
     * @param mixed $v
     * @return array|null
     */
    private function decodeJsonNullable(mixed $v): ?array
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_array($v)) {
            return $v;
        }

        $decoded = json_decode((string)$v, true);
        return is_array($decoded) ? $decoded : null;
    }
}
