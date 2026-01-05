<?php
declare(strict_types=1);

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

namespace App\Service\Address;

use App\ServiceInterface\Address\AddressValidatedApplierInterface;
use DateTimeImmutable;
use PDO;
use Throwable;

final class AddressValidatedApplier implements AddressValidatedApplierInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string, mixed> $data */
    private function fingerprint(array $data): string
    {
        $keys = [
            'line1Norm',
            'cityNorm',
            'regionNorm',
            'postalCodeNorm',
            'latitude',
            'longitude',
            'geohash',
            'validationProvider',
            'validatedAt',
            'dedupeKey',
        ];

        $arr = [];
        foreach ($keys as $k) {
            $arr[$k] = $data[$k] ?? null;
        }

        $json = json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{}';
        }

        return hash('sha256', $json);
    }

    /** @param array<string, mixed> $data */
    public function apply(string $id, array $data): void
    {
        $fp = $this->fingerprint($data);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT validation_fingerprint FROM address_entity WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $prev = $stmt->fetchColumn();
            if ($prev !== false && $prev !== null && (string)$prev === $fp) {
                $this->pdo->rollBack();
                return; // idempotent
            }

            $validatedAt = $data['validatedAt'] ?? null;
            if ($validatedAt === null) {
                $validatedAt = (new DateTimeImmutable())->format(DATE_ATOM);
            }

            $upd = $this->pdo->prepare(
                'UPDATE address_entity SET '
                . 'line1_norm = :l1, city_norm = :city, region_norm = :region, postal_code_norm = :pc, '
                . 'latitude = :lat, longitude = :lng, geohash = :gh, '
                . "validation_status = 'validated', validation_provider = :vp, validated_at = :va, "
                . 'dedupe_key = :dk, validation_fingerprint = :fp, updated_at = now() '
                . 'WHERE id = :id'
            );

            $upd->execute([
                ':id' => $id,
                ':l1' => $data['line1Norm'] ?? null,
                ':city' => $data['cityNorm'] ?? null,
                ':region' => $data['regionNorm'] ?? null,
                ':pc' => $data['postalCodeNorm'] ?? null,
                ':lat' => $data['latitude'] ?? null,
                ':lng' => $data['longitude'] ?? null,
                ':gh' => $data['geohash'] ?? null,
                ':vp' => $data['validationProvider'] ?? null,
                ':va' => $validatedAt,
                ':dk' => $data['dedupeKey'] ?? null,
                ':fp' => $fp,
            ]);

            $ins = $this->pdo->prepare(
                'INSERT INTO address_audit '
                . '(address_id, action, before_hash, after_hash, changed_at, meta) '
                . 'VALUES (:id, :action, :before, :after, now(), :meta)'
            );
            $ins->execute([
                ':id' => $id,
                ':action' => 'validated_apply',
                ':before' => $prev === false ? null : $prev,
                ':after' => $fp,
                ':meta' => json_encode(['provider' => $data['validationProvider'] ?? null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
