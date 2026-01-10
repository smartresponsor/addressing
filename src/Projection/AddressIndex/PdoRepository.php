<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\Projection\AddressIndex;

use PDO;

/**
 *
 */

/**
 *
 */
final class PdoRepository implements RepositoryInterface
{
    /**
     * @param \PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * @param \App\Projection\AddressIndex\IndexRecord $r
     * @return void
     */
    public function upsert(IndexRecord $r): void
    {
        $sql = 'INSERT INTO address_index
            (digest,line1,line2,city,region,postal,country,lat,lon,display,provider,confidence,geo_key,created_at,updated_at)
            VALUES (:digest,:line1,:line2,:city,:region,:postal,:country,:lat,:lon,:display,:provider,:confidence,:geo_key,:created_at,:updated_at)
            ON CONFLICT(digest) DO UPDATE SET
                line1=excluded.line1,line2=excluded.line2,city=excluded.city,region=excluded.region,postal=excluded.postal,country=excluded.country,
                lat=excluded.lat,lon=excluded.lon,display=excluded.display,provider=excluded.provider,confidence=excluded.confidence,geo_key=excluded.geo_key,
                updated_at=excluded.updated_at';
        // Note: For MySQL replace ON CONFLICT with ON DUPLICATE KEY UPDATE and placeholders accordingly.
        $stmt = $this->pdo->prepare($sql);
        $arr = $r->toArray();
        $stmt->execute([
            ':digest' => $arr['digest'], ':line1' => $arr['line1'], ':line2' => $arr['line2'], ':city' => $arr['city'],
            ':region' => $arr['region'], ':postal' => $arr['postal'], ':country' => $arr['country'],
            ':lat' => $arr['lat'], ':lon' => $arr['lon'], ':display' => $arr['display'], ':provider' => $arr['provider'],
            ':confidence' => $arr['confidence'], ':geo_key' => $arr['geo_key'], ':created_at' => $arr['created_at'], ':updated_at' => $arr['updated_at'],
        ]);
    }

    /**
     * @param string $digest
     * @return \App\Projection\AddressIndex\IndexRecord|null
     */
    public function getByDigest(string $digest): ?IndexRecord
    {
        $stmt = $this->pdo->prepare('SELECT * FROM address_index WHERE digest = :d LIMIT 1');
        $stmt->execute([':d' => $digest]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param string $prefix
     * @param string|null $country
     * @param int $limit
     * @return array<\App\Projection\AddressIndex\IndexRecord>
     */
    public function search(string $prefix, ?string $country = null, int $limit = 20): array
    {
        $like = $prefix . '%';
        if ($country) {
            $stmt = $this->pdo->prepare('SELECT * FROM address_index WHERE country = :c AND (city LIKE :q OR region LIKE :q OR postal LIKE :q OR line1 LIKE :q) ORDER BY updated_at DESC LIMIT :lim');
            $stmt->bindValue(':c', strtoupper($country));
            $stmt->bindValue(':q', $like);
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM address_index WHERE (city LIKE :q OR region LIKE :q OR postal LIKE :q OR line1 LIKE :q) ORDER BY updated_at DESC LIMIT :lim');
            $stmt->bindValue(':q', $like);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = $this->hydrate($row);
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return \App\Projection\AddressIndex\IndexRecord
     */
    private function hydrate(array $row): IndexRecord
    {
        return new IndexRecord(
            $row['digest'], $row['line1'], $row['line2'] ?? null, $row['city'], $row['region'], $row['postal'], $row['country'],
            isset($row['lat']) && $row['lat'] !== null ? (float)$row['lat'] : null,
            isset($row['lon']) && $row['lon'] !== null ? (float)$row['lon'] : null,
            $row['display'] ?? null, $row['provider'] ?? null,
            isset($row['confidence']) && $row['confidence'] !== null ? (float)$row['confidence'] : null,
            $row['geo_key'] ?? '', $row['created_at'], $row['updated_at']
        );
    }
}
