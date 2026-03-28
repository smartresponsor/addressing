<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Projection\AddressIndex;

use PDO;

/**
 *
 */

/**
 *
 */
final readonly class PdoRepository implements RepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    #[\Override]
    public function upsert(IndexRecord $indexRecord): void
    {
        $sql = 'INSERT INTO address_index
            (digest,line1,line2,city,region,postal,country,lat,lon,display,provider,confidence,geo_key,created_at,updated_at)
            VALUES (:digest,:line1,:line2,:city,:region,:postal,:country,:lat,:lon,:display,:provider,:confidence,:geo_key,:created_at,:updated_at)
            ON CONFLICT(digest) DO UPDATE SET
                line1=excluded.line1,line2=excluded.line2,city=excluded.city,region=excluded.region,postal=excluded.postal,country=excluded.country,
                lat=excluded.lat,lon=excluded.lon,display=excluded.display,provider=excluded.provider,confidence=excluded.confidence,geo_key=excluded.geo_key,
                updated_at=excluded.updated_at';
        // Note: For MySQL replace ON CONFLICT with ON DUPLICATE KEY UPDATE and placeholders accordingly.
        $pdoStatement = $this->prepare($sql);
        $arr = $indexRecord->toArray();
        $pdoStatement->execute([
            ':digest' => $arr['digest'], ':line1' => $arr['line1'], ':line2' => $arr['line2'], ':city' => $arr['city'],
            ':region' => $arr['region'], ':postal' => $arr['postal'], ':country' => $arr['country'],
            ':lat' => $arr['lat'], ':lon' => $arr['lon'], ':display' => $arr['display'], ':provider' => $arr['provider'],
            ':confidence' => $arr['confidence'], ':geo_key' => $arr['geo_key'], ':created_at' => $arr['created_at'], ':updated_at' => $arr['updated_at'],
        ]);
    }

    #[\Override]
    public function getByDigest(string $digest): ?IndexRecord
    {
        $pdoStatement = $this->prepare('SELECT * FROM address_index WHERE digest = :d LIMIT 1');
        $pdoStatement->execute([':d' => $digest]);
        $row = $pdoStatement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        /** @var array<string, mixed> $row */
        return $this->hydrate($row);
    }

    /**
     * @return array<\App\Projection\AddressIndex\IndexRecord>
     */
    #[\Override]
    public function search(string $prefix, ?string $country = null, int $limit = 20): array
    {
        $like = $prefix . '%';
        if ($country) {
            $stmt = $this->prepare('SELECT * FROM address_index WHERE country = :c AND (city LIKE :q OR region LIKE :q OR postal LIKE :q OR line1 LIKE :q) ORDER BY updated_at DESC LIMIT :lim');
            $stmt->bindValue(':c', strtoupper($country));
            $stmt->bindValue(':q', $like);
        } else {
            $stmt = $this->prepare('SELECT * FROM address_index WHERE (city LIKE :q OR region LIKE :q OR postal LIKE :q OR line1 LIKE :q) ORDER BY updated_at DESC LIMIT :lim');
            $stmt->bindValue(':q', $like);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            /** @var array<string, mixed> $row */
            $out[] = $this->hydrate($row);
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): IndexRecord
    {
        return new IndexRecord(
            $this->asString($row['digest'] ?? null, 'digest'),
            $this->asString($row['line1'] ?? null, 'line1'),
            $this->asNullableString($row['line2'] ?? null),
            $this->asString($row['city'] ?? null, 'city'),
            $this->asString($row['region'] ?? null, 'region'),
            $this->asString($row['postal'] ?? null, 'postal'),
            $this->asString($row['country'] ?? null, 'country'),
            $this->asNullableFloat($row['lat'] ?? null),
            $this->asNullableFloat($row['lon'] ?? null),
            $this->asNullableString($row['display'] ?? null),
            $this->asNullableString($row['provider'] ?? null),
            $this->asNullableFloat($row['confidence'] ?? null),
            $this->asString($row['geo_key'] ?? null, 'geo_key'),
            $this->asString($row['created_at'] ?? null, 'created_at'),
            $this->asString($row['updated_at'] ?? null, 'updated_at')
        );
    }

    private function asString(mixed $value, string $field): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string)$value;
        }
        throw new \RuntimeException('invalid_' . $field);
    }

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

    private function prepare(string $sql): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        if ($stmt === false) {
            throw new \RuntimeException('prepare_failed');
        }
        return $stmt;
    }
}
