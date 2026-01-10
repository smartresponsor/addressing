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

/**
 *
 */
final class AddressRepository implements AddressRepositoryInterface
{
    /**
     * @param \PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
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

        $this->runInTransaction(function () use ($address, $sql): void {
            $stmt = $this->pdo->prepare($sql);
            $this->bind($stmt, $address);
            $stmt->execute();

            $this->replaceLocalizations($address->id(), $address->line1Localized(), $address->cityLocalized());

            $this->appendOutbox('AddressCreated', [
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

        $this->runInTransaction(function () use ($address, $sql): void {
            $stmt = $this->pdo->prepare($sql);
            $this->bind($stmt, $address);
            $stmt->execute();

            $this->replaceLocalizations($address->id(), $address->line1Localized(), $address->cityLocalized());

            $this->appendOutbox('AddressUpdated', [
                'id' => $address->id(),
                'updatedAt' => $address->updatedAt() ?? (new DateTimeImmutable())->format(DATE_ATOM),
            ]);
        });
    }

    /**
     * @param string $id
     * @return \App\EntityInterface\Address\AddressInterface|null
     */
    public function get(string $id): ?AddressInterface
    {
        $stmt = $this->pdo->prepare('SELECT * FROM address_entity WHERE id=:id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }
        $localized = $this->fetchLocalizations($id);
        return $this->map($row, $localized['line1Localized'], $localized['cityLocalized']);
    }

    /**
     * @param string $id
     * @return void
     */
    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE address_entity SET deleted_at=now() WHERE id=:id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);

        $this->appendOutbox('AddressDeleted', [
            'id' => $id,
            'deletedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
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
        $items = array_map(function (array $r): AddressInterface {
            $localized = $this->fetchLocalizations((string) $r['id']);
            return $this->map($r, $localized['line1Localized'], $localized['cityLocalized']);
        }, $rows);

        $nextCursor = null;
        if (count($rows) === $limit && $rows !== []) {
            $last = end($rows);
            if (is_array($last) && isset($last['id'])) {
                $nextCursor = (string) $last['id'];
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
     * @param array<string, mixed> $r
     * @param array<string, string>|null $line1Localized
     * @param array<string, string>|null $cityLocalized
     * @return \App\Entity\Address\AddressData
     */
    private function map(array $r, ?array $line1Localized, ?array $cityLocalized): AddressData
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
            $line1Localized,
            $cityLocalized,
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

    /**
     * @param string $addressId
     * @param array<string, string>|null $line1Localized
     * @param array<string, string>|null $cityLocalized
     * Empty maps are treated as null. Empty string values are preserved as-is.
     * @return void
     */
    public function replaceLocalizations(string $addressId, ?array $line1Localized, ?array $cityLocalized): void
    {
        $line1Localized = $this->assertLocalizedMap($line1Localized, 'line1Localized');
        $cityLocalized = $this->assertLocalizedMap($cityLocalized, 'cityLocalized');

        $stmt = $this->pdo->prepare('DELETE FROM address_localization WHERE address_id = :id');
        $stmt->execute([':id' => $addressId]);

        if ($line1Localized === null && $cityLocalized === null) {
            return;
        }

        $locales = array_unique(array_merge(
            array_keys($line1Localized ?? []),
            array_keys($cityLocalized ?? [])
        ));
        if ($locales === []) {
            return;
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:sP');
        $insert = $this->pdo->prepare(
            'INSERT INTO address_localization (address_id, locale, line1, city, created_at, updated_at)
             VALUES (:address_id, :locale, :line1, :city, :created_at, :updated_at)'
        );

        foreach ($locales as $locale) {
            $insert->execute([
                ':address_id' => $addressId,
                ':locale' => $locale,
                ':line1' => $line1Localized[$locale] ?? null,
                ':city' => $cityLocalized[$locale] ?? null,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
    }

    /**
     * @param string $addressId
     * @return array{line1Localized: ?array<string, string>, cityLocalized: ?array<string, string>}
     */
    public function fetchLocalizations(string $addressId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT locale, line1, city FROM address_localization WHERE address_id = :id ORDER BY locale ASC'
        );
        $stmt->execute([':id' => $addressId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $line1Localized = [];
        $cityLocalized = [];

        foreach ($rows as $row) {
            $locale = (string) $row['locale'];
            if (array_key_exists('line1', $row) && $row['line1'] !== null) {
                $line1Localized[$locale] = (string) $row['line1'];
            }
            if (array_key_exists('city', $row) && $row['city'] !== null) {
                $cityLocalized[$locale] = (string) $row['city'];
            }
        }

        return [
            'line1Localized' => $line1Localized === [] ? null : $line1Localized,
            'cityLocalized' => $cityLocalized === [] ? null : $cityLocalized,
        ];
    }

    /**
     * @param array<string, mixed>|null $map
     * @param string $field
     * @return array<string, string>|null
     */
    private function assertLocalizedMap(?array $map, string $field): ?array
    {
        if ($map === null) {
            return null;
        }
        $out = [];
        foreach ($map as $locale => $value) {
            if (!is_string($locale)) {
                throw new RuntimeException('invalid_' . $field . '_key');
            }
            if (!is_string($value)) {
                throw new RuntimeException('invalid_' . $field . '_value');
            }
            $out[$locale] = $value;
        }
        return $out === [] ? null : $out;
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

    /**
     * @param string $name
     * @param array<string, mixed> $payload
     * @return void
     */
    private function appendOutbox(string $name, array $payload): void
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            throw new RuntimeException('payload_encode_failed');
        }
        $payloadExpr = ':payload';
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $payloadExpr = ':payload::jsonb';
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO address_outbox(event_name, event_version, payload) VALUES (:name, :ver, ' . $payloadExpr . ')'
        );
        $stmt->execute([
            ':name' => $name,
            ':ver' => 1,
            ':payload' => $payloadJson,
        ]);
    }

    /**
     * @param callable(): void $fn
     * @return void
     */
    private function runInTransaction(callable $fn): void
    {
        $inTransaction = $this->pdo->inTransaction();
        if (!$inTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $fn();
            if (!$inTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$inTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
