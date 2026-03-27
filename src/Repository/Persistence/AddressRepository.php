<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Repository\Persistence;

use App\Entity\Record\AddressData;
use App\EntityInterface\Record\AddressInterface;
use App\RepositoryInterface\Persistence\AddressRepositoryInterface;

final readonly class AddressRepository implements AddressRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function create(AddressInterface $address): void
    {
        $this->pdo->beginTransaction();
        try {
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

            $stmt = $this->prepare($sql);
            $this->bindForCreate($stmt, $address);
            $stmt->execute();

            $this->appendOutbox('AddressCreated', [
                'id' => $address->id(),
                'ownerId' => $address->ownerId(),
                'vendorId' => $address->vendorId(),
                'countryCode' => $address->countryCode(),
                'createdAt' => $address->createdAt(),
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function update(AddressInterface $address): void
    {
        $this->ensureTenantScope($address->ownerId(), $address->vendorId());
        $tenantWhere = $this->tenantWhereClause($address->ownerId(), $address->vendorId());
        $this->pdo->beginTransaction();
        try {
            $sql = <<<'SQL'
UPDATE address_entity SET
    owner_id=:owner_id, vendor_id=:vendor_id, line1=:line1, line2=:line2, city=:city, region=:region,
    postal_code=:postal_code, country_code=:country_code,
    line1_norm=:line1_norm, city_norm=:city_norm, region_norm=:region_norm, postal_code_norm=:postal_code_norm,
    latitude=:latitude, longitude=:longitude, geohash=:geohash,
    validation_status=:validation_status, validation_provider=:validation_provider, validated_at=:validated_at,
    dedupe_key=:dedupe_key, updated_at=:updated_at, deleted_at=:deleted_at
WHERE id=:id AND %s
SQL;

            $stmt = $this->prepare(sprintf($sql, $tenantWhere));
            $this->bindForUpdate($stmt, $address);
            $stmt->execute();
            if (0 === $stmt->rowCount()) {
                $this->pdo->rollBack();

                return;
            }

            $this->appendOutbox('AddressUpdated', [
                'id' => $address->id(),
                'updatedAt' => $address->updatedAt() ?? (new \DateTimeImmutable())->format(DATE_ATOM),
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function get(string $id, ?string $ownerId, ?string $vendorId): ?AddressInterface
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $params = array_merge([':id' => $id], $this->tenantParams($ownerId, $vendorId));
        $stmt = $this->prepare(
            'SELECT * FROM address_entity WHERE id=:id AND deleted_at IS NULL AND '
            .$this->tenantWhereClause($ownerId, $vendorId)
        );
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        /* @var array<string, mixed> $row */
        return $this->map($row);
    }

    public function delete(string $id, ?string $ownerId, ?string $vendorId): void
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $params = array_merge([':id' => $id], $this->tenantParams($ownerId, $vendorId));
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->prepare(
                'UPDATE address_entity SET deleted_at='.$this->currentTimestampSql().' WHERE id=:id AND deleted_at IS NULL AND '
                .$this->tenantWhereClause($ownerId, $vendorId)
            );
            $stmt->execute($params);
            if (0 === $stmt->rowCount()) {
                $this->pdo->rollBack();

                return;
            }

            $this->appendOutbox('AddressDeleted', [
                'id' => $id,
                'deletedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function findByDedupeKey(string $dedupeKey): ?AddressInterface
    {
        $dedupeKey = trim($dedupeKey);
        if ('' === $dedupeKey) {
            return null;
        }

        $stmt = $this->prepare('SELECT * FROM address_entity WHERE dedupe_key = :dedupe AND deleted_at IS NULL');
        $stmt->execute([':dedupe' => $dedupeKey]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        /* @var array<string, mixed> $row */
        return $this->map($row);
    }

    public function markDeleted(string $id, ?string $ownerId, ?string $vendorId): void
    {
        $this->delete($id, $ownerId, $vendorId);
    }

    /**
     * @return array{items: AddressInterface[], nextCursor: ?string}
     */
    public function findPage(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, int $limit, ?string $cursor): array
    {
        $this->ensureTenantScope($ownerId, $vendorId);
        $limit = max(1, min(200, $limit));
        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driverAttr) ? $driverAttr : '';
        $params = $this->tenantParams($ownerId, $vendorId);
        $where = ['deleted_at IS NULL', $this->tenantWhereClause($ownerId, $vendorId)];
        if ($countryCode) {
            $where[] = 'country_code = :country_code';
            $params[':country_code'] = $countryCode;
        }
        if ($q) {
            $op = 'pgsql' === $driver ? 'ILIKE' : 'LIKE';
            $where[] = "lower(line1 || ' ' || city || ' ' || coalesce(postal_code,'')) {$op} lower(:q)";
            $params[':q'] = '%'.$q.'%';
        }
        if ($cursor) {
            $where[] = 'id > :cursor';
            $params[':cursor'] = $cursor;
        }

        $sql = 'SELECT * FROM address_entity WHERE '.implode(' AND ', $where).' ORDER BY id ASC LIMIT :limit';
        $stmt = $this->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        /** @var array<int, array<string, mixed>> $safeRows */
        $safeRows = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row)) {
                    /* @var array<string, mixed> $row */
                    $safeRows[] = $row;
                }
            }
        }
        $items = array_map(fn (array $r): AddressInterface => $this->map($r), $safeRows);

        $nextCursor = null;
        if (count($safeRows) === $limit && [] !== $safeRows) {
            $last = end($safeRows);
            if (is_array($last) && isset($last['id'])) {
                $nextCursor = (string) $last['id'];
            }
        }

        return ['items' => $items, 'nextCursor' => $nextCursor];
    }

    private function bindForCreate(\PDOStatement $stmt, AddressInterface $a): void
    {
        $normalized = $this->normalizedFields($a);

        $stmt->bindValue(':id', $a->id());
        $stmt->bindValue(':owner_id', $a->ownerId());
        $stmt->bindValue(':vendor_id', $a->vendorId());
        $stmt->bindValue(':line1', $a->line1());
        $stmt->bindValue(':line2', $a->line2());
        $stmt->bindValue(':city', $a->city());
        $stmt->bindValue(':region', $a->region());
        $stmt->bindValue(':postal_code', $a->postalCode());
        $stmt->bindValue(':country_code', $a->countryCode());
        $stmt->bindValue(':line1_norm', $normalized['line1_norm']);
        $stmt->bindValue(':city_norm', $normalized['city_norm']);
        $stmt->bindValue(':region_norm', $normalized['region_norm']);
        $stmt->bindValue(':postal_code_norm', $normalized['postal_code_norm']);
        $stmt->bindValue(':latitude', $a->latitude());
        $stmt->bindValue(':longitude', $a->longitude());
        $stmt->bindValue(':geohash', $a->geohash());
        $stmt->bindValue(':validation_status', $a->validationStatus());
        $stmt->bindValue(':validation_provider', $a->validationProvider());
        $stmt->bindValue(':validated_at', $a->validatedAt());
        $stmt->bindValue(':dedupe_key', $this->effectiveDedupeKey($a, $normalized));
        $stmt->bindValue(':created_at', $a->createdAt());
        $stmt->bindValue(':updated_at', $a->updatedAt());
        $stmt->bindValue(':deleted_at', $a->deletedAt());
    }

    private function bindForUpdate(\PDOStatement $stmt, AddressInterface $a): void
    {
        $normalized = $this->normalizedFields($a);

        $stmt->bindValue(':id', $a->id());
        $stmt->bindValue(':owner_id', $a->ownerId());
        $stmt->bindValue(':vendor_id', $a->vendorId());
        $stmt->bindValue(':line1', $a->line1());
        $stmt->bindValue(':line2', $a->line2());
        $stmt->bindValue(':city', $a->city());
        $stmt->bindValue(':region', $a->region());
        $stmt->bindValue(':postal_code', $a->postalCode());
        $stmt->bindValue(':country_code', $a->countryCode());
        $stmt->bindValue(':line1_norm', $normalized['line1_norm']);
        $stmt->bindValue(':city_norm', $normalized['city_norm']);
        $stmt->bindValue(':region_norm', $normalized['region_norm']);
        $stmt->bindValue(':postal_code_norm', $normalized['postal_code_norm']);
        $stmt->bindValue(':latitude', $a->latitude());
        $stmt->bindValue(':longitude', $a->longitude());
        $stmt->bindValue(':geohash', $a->geohash());
        $stmt->bindValue(':validation_status', $a->validationStatus());
        $stmt->bindValue(':validation_provider', $a->validationProvider());
        $stmt->bindValue(':validated_at', $a->validatedAt());
        $stmt->bindValue(':dedupe_key', $this->effectiveDedupeKey($a, $normalized));
        $stmt->bindValue(':updated_at', $a->updatedAt());
        $stmt->bindValue(':deleted_at', $a->deletedAt());
    }

    /**
     * @param array<string, mixed> $r
     */
    private function map(array $r): AddressData
    {
        $validationRaw = $this->decodeJsonNullable($r['validation_raw'] ?? null);
        $validationVerdict = $this->decodeJsonNullable($r['validation_verdict'] ?? null);
        $validationDeliverable = $this->asNullableBool($r['validation_deliverable'] ?? null);
        $validationGranularity = $this->asNullableString($r['validation_granularity'] ?? null);
        $validationQuality = $this->asNullableInt($r['validation_quality'] ?? null);

        return new AddressData(
            $this->asString($r['id'] ?? null, 'id'),
            $this->asNullableString($r['owner_id'] ?? null),
            $this->asNullableString($r['vendor_id'] ?? null),
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

    /**
     * @return array{line1_norm: ?string, city_norm: ?string, region_norm: ?string, postal_code_norm: ?string}
     */
    private function normalizedFields(AddressInterface $address): array
    {
        return [
            'line1_norm' => $this->normalizeText($address->line1Norm(), $address->line1()),
            'city_norm' => $this->normalizeText($address->cityNorm(), $address->city()),
            'region_norm' => $this->normalizeText($address->regionNorm(), $address->region()),
            'postal_code_norm' => $this->normalizeText($address->postalCodeNorm(), $address->postalCode()),
        ];
    }

    /**
     * @param array{line1_norm: ?string, city_norm: ?string, region_norm: ?string, postal_code_norm: ?string} $normalized
     */
    private function effectiveDedupeKey(AddressInterface $address, array $normalized): ?string
    {
        $dedupeKey = $this->normalizeOptionalScalar($address->dedupeKey());
        if (null !== $dedupeKey) {
            return $dedupeKey;
        }

        if (
            null === $normalized['line1_norm']
            && null === $normalized['city_norm']
            && null === $normalized['region_norm']
            && null === $normalized['postal_code_norm']
        ) {
            return null;
        }

        return implode('|', [
            $normalized['line1_norm'] ?? '',
            $normalized['city_norm'] ?? '',
            $normalized['region_norm'] ?? '',
            $normalized['postal_code_norm'] ?? '',
            strtoupper(trim($address->countryCode())),
            $this->normalizeOptionalScalar($address->ownerId()) ?? '',
            $this->normalizeOptionalScalar($address->vendorId()) ?? '',
        ]);
    }

    private function normalizeText(?string $normalized, ?string $raw): ?string
    {
        $value = $this->normalizeOptionalScalar($normalized);
        if (null !== $value) {
            return $value;
        }

        return $this->normalizeOptionalScalar($raw);
    }

    private function normalizeOptionalScalar(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        return strtolower(preg_replace('/\s+/', '', $value) ?? $value);
    }

    private function currentTimestampSql(): string
    {
        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driverAttr) ? $driverAttr : '';

        return 'pgsql' === $driver ? 'now()' : 'CURRENT_TIMESTAMP';
    }

    /** @return array<string, mixed>|null */
    private function decodeJsonNullable(mixed $v): ?array
    {
        if (null === $v) {
            return null;
        }
        if (is_array($v)) {
            /* @var array<string, mixed> $v */
            return $v;
        }
        if (!is_string($v) && !is_int($v) && !is_float($v) && !is_bool($v)) {
            return null;
        }
        $s = (string) $v;
        $s = trim($s);
        if ('' === $s) {
            return null;
        }
        $decoded = json_decode($s, true);
        if (!is_array($decoded)) {
            return null;
        }

        /* @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function asString(mixed $value, string $field): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }
        throw new \RuntimeException('invalid_'.$field);
    }

    private function asNullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return null;
    }

    private function asNullableFloat(mixed $value): ?float
    {
        if (null === $value) {
            return null;
        }
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function asNullableInt(mixed $value): ?int
    {
        if (null === $value) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function asNullableBool(mixed $value): ?bool
    {
        if (null === $value) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return 1 === $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return ((int) $value) === 1;
        }

        return null;
    }

    /**
     * @param non-empty-string $name
     * @param array<mixed>     $payload
     */
    private function appendOutbox(string $name, array $payload = []): void
    {
        $payloadJson = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if (false === $payloadJson) {
            throw new \RuntimeException('payload_encode_failed');
        }

        if (!$this->pdo instanceof \PDO) {
            throw new LogicException('PDO not initialized');
        }

        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driverAttr) ? $driverAttr : '';

        $payloadExpr = 'pgsql' === $driver
            ? ':payload::jsonb'
            : ':payload';

        $sql = "
        INSERT INTO address_outbox (event_name, event_version, payload)
        VALUES (:name, :ver, {$payloadExpr})
    ";

        $stmt = $this->pdo->prepare($sql);

        if (false === $stmt) {
            throw new \RuntimeException('outbox_prepare_failed');
        }

        $stmt->execute([
            ':name' => $name,
            ':ver' => 1,
            ':payload' => $payloadJson,
        ]);
    }

    private function prepare(string $sql): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        if (false === $stmt) {
            throw new \RuntimeException('prepare_failed');
        }

        return $stmt;
    }

    private function ensureTenantScope(?string $ownerId, ?string $vendorId): void
    {
        if (null === $ownerId && null === $vendorId) {
            throw new \RuntimeException('tenant_scope_required');
        }
    }

    private function tenantWhereClause(?string $ownerId, ?string $vendorId): string
    {
        $clauses = [];
        if (null !== $ownerId) {
            $clauses[] = 'owner_id = :owner_id';
        }
        if (null !== $vendorId) {
            $clauses[] = 'vendor_id = :vendor_id';
        }

        return '('.implode(' AND ', $clauses).')';
    }

    /**
     * @return array<string, string|null>
     */
    private function tenantParams(?string $ownerId, ?string $vendorId): array
    {
        $params = [];
        if (null !== $ownerId) {
            $params[':owner_id'] = $ownerId;
        }
        if (null !== $vendorId) {
            $params[':vendor_id'] = $vendorId;
        }

        return $params;
    }
}
