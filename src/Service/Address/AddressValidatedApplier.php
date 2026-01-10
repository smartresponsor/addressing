<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */
declare(strict_types=1);

namespace App\Service\Address;

use App\Contract\Address\AddressValidated;
use App\ServiceInterface\Address\AddressValidatedApplierInterface;
use PDO;
use PDOException;
use RuntimeException;

final class AddressValidatedApplier implements AddressValidatedApplierInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function apply(string $id, AddressValidated $validated): void
    {
        $fingerprint = $validated->fingerprint();
        $now = new \DateTimeImmutable('now');
        $validatedAt = $validated->validatedAt ?? $now;

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('SELECT validation_fingerprint FROM address_entity WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $this->pdo->rollBack();
                throw new RuntimeException('not_found');
            }

            $prev = $row['validation_fingerprint'] ?? null;
            if (is_string($prev) && $prev !== '' && $prev === $fingerprint) {
                $this->pdo->commit();
                return;
            }

            $fields = [];
            $params = [
                ':id' => $id,
                ':updated_at' => $now->format('Y-m-d H:i:sP'),
                ':validation_provider' => $validated->validationProvider,
                ':validation_status' => 'validated',
                ':validated_at' => $validatedAt->format('Y-m-d H:i:sP'),
                ':dedupe_key' => $validated->dedupeKey,
                ':validation_fingerprint' => $fingerprint,
            ];

            if ($validated->line1Norm !== null) {
                $fields[] = 'line1_norm = :line1_norm';
                $params[':line1_norm'] = $validated->line1Norm;
            }
            if ($validated->cityNorm !== null) {
                $fields[] = 'city_norm = :city_norm';
                $params[':city_norm'] = $validated->cityNorm;
            }
            if ($validated->regionNorm !== null) {
                $fields[] = 'region_norm = :region_norm';
                $params[':region_norm'] = $validated->regionNorm;
            }
            if ($validated->postalCodeNorm !== null) {
                $fields[] = 'postal_code_norm = :postal_code_norm';
                $params[':postal_code_norm'] = $validated->postalCodeNorm;
            }
            if ($validated->latitude !== null) {
                $fields[] = 'latitude = :latitude';
                $params[':latitude'] = $validated->latitude;
            }
            if ($validated->longitude !== null) {
                $fields[] = 'longitude = :longitude';
                $params[':longitude'] = $validated->longitude;
            }
            if ($validated->geohash !== null) {
                $fields[] = 'geohash = :geohash';
                $params[':geohash'] = $validated->geohash;
            }

            if ($validated->raw !== null) {
                $fields[] = 'validation_raw = :validation_raw::jsonb';
                $params[':validation_raw'] = json_encode($validated->raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $params[':validation_raw_sha256'] = hash('sha256', $params[':validation_raw']);
            }
            if ($validated->verdict !== null) {
                $fields[] = 'validation_verdict = :validation_verdict::jsonb';
                $params[':validation_verdict'] = json_encode($validated->verdict->jsonSerialize(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if ($validated->verdict->deliverable !== null) {
                    $fields[] = 'validation_deliverable = :validation_deliverable';
                    $params[':validation_deliverable'] = $validated->verdict->deliverable ? 1 : 0;
                }
                if ($validated->verdict->granularity !== null) {
                    $fields[] = 'validation_granularity = :validation_granularity';
                    $params[':validation_granularity'] = $validated->verdict->granularity;
                }
                if ($validated->verdict->quality !== null) {
                    $fields[] = 'validation_quality = :validation_quality';
                    $params[':validation_quality'] = $validated->verdict->quality;
                }
            }

            $fields[] = 'validation_provider = :validation_provider';
            $fields[] = 'validation_status = :validation_status';
            $fields[] = 'validated_at = :validated_at';
            $fields[] = 'dedupe_key = :dedupe_key';
            $fields[] = 'validation_fingerprint = :validation_fingerprint';
            $fields[] = 'updated_at = :updated_at';

            $sql = 'UPDATE address_entity SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute($params);

            if (!$ok) {
                $this->pdo->rollBack();
                throw new RuntimeException('apply_failed');
            }
            if ($stmt->rowCount() < 1) {
                $this->pdo->rollBack();
                throw new RuntimeException('not_found');
            }

            $this->appendOutbox('AddressValidatedApplied', 1, [
                'id' => $id,
                'fingerprint' => $fingerprint,
                'provider' => $validated->validationProvider,
                'validatedAt' => $validatedAt->format(DATE_ATOM),
                'deliverable' => $validated->verdict?->deliverable,
                'granularity' => $validated->verdict?->granularity,
                'quality' => $validated->verdict?->quality,
                'rawSha256' => $params[':validation_raw_sha256'] ?? null,
            ]);

            $this->pdo->commit();
        } catch (PDOException) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('apply_failed');
        }
    }

    /** @param array<string, mixed> $payload */
    private function appendOutbox(string $name, int $version, array $payload): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $payloadExpr = $driver === 'pgsql' ? ':payload::jsonb' : ':payload';
        $stmt = $this->pdo->prepare(
            "INSERT INTO address_outbox(event_name, event_version, payload) VALUES (:name, :ver, {$payloadExpr})"
        );
        $stmt->execute([
            ':name' => $name,
            ':ver' => $version,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
