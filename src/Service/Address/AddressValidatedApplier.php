<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Address;

use App\Contract\Address\AddressValidated;
use App\ServiceInterface\Address\AddressValidatedApplierInterface;

final class AddressValidatedApplier implements AddressValidatedApplierInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function apply(string $id, AddressValidated $validated, ?string $ownerId = null, ?string $vendorId = null): void
    {
        $fingerprint = $validated->fingerprint();
        $now = new \DateTimeImmutable('now');
        $validatedAt = $validated->validatedAt ?? $now;
        $scopeParams = $this->tenantParams($ownerId, $vendorId);
        $scopeWhere = $this->tenantWhereClause($ownerId, $vendorId);
        $lockClause = $this->isPgsql() ? ' FOR UPDATE' : '';

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->prepare('SELECT validation_fingerprint FROM address_entity WHERE id = :id AND '.$scopeWhere.$lockClause);
            $stmt->execute(array_merge([':id' => $id], $scopeParams));
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                $this->pdo->rollBack();
                throw new \RuntimeException('not_found');
            }

            /** @var array<string, mixed> $row */
            $prev = $row['validation_fingerprint'] ?? null;
            if (is_string($prev) && '' !== $prev && $prev === $fingerprint) {
                $this->pdo->commit();

                return;
            }

            $fields = [];
            $params = array_merge([
                ':id' => $id,
                ':updated_at' => $now->format('Y-m-d H:i:sP'),
                ':validation_provider' => $validated->validationProvider,
                ':validation_status' => 'validated',
                ':validated_at' => $validatedAt->format('Y-m-d H:i:sP'),
                ':dedupe_key' => $validated->dedupeKey,
                ':validation_fingerprint' => $fingerprint,
            ], $scopeParams);

            if (null !== $validated->line1Norm) {
                $fields[] = 'line1_norm = :line1_norm';
                $params[':line1_norm'] = $validated->line1Norm;
            }
            if (null !== $validated->cityNorm) {
                $fields[] = 'city_norm = :city_norm';
                $params[':city_norm'] = $validated->cityNorm;
            }
            if (null !== $validated->regionNorm) {
                $fields[] = 'region_norm = :region_norm';
                $params[':region_norm'] = $validated->regionNorm;
            }
            if (null !== $validated->postalCodeNorm) {
                $fields[] = 'postal_code_norm = :postal_code_norm';
                $params[':postal_code_norm'] = $validated->postalCodeNorm;
            }
            if (null !== $validated->latitude) {
                $fields[] = 'latitude = :latitude';
                $params[':latitude'] = $validated->latitude;
            }
            if (null !== $validated->longitude) {
                $fields[] = 'longitude = :longitude';
                $params[':longitude'] = $validated->longitude;
            }
            if (null !== $validated->geohash) {
                $fields[] = 'geohash = :geohash';
                $params[':geohash'] = $validated->geohash;
            }

            if (null !== $validated->raw) {
                $fields[] = $this->jsonAssignment('validation_raw', ':validation_raw');
                $rawJson = $this->encodePayload($validated->raw);
                $params[':validation_raw'] = $rawJson;
                $params[':validation_raw_sha256'] = hash('sha256', $rawJson);
            }
            if (null !== $validated->verdict) {
                $fields[] = $this->jsonAssignment('validation_verdict', ':validation_verdict');
                $params[':validation_verdict'] = $this->encodePayload($validated->verdict->jsonSerialize());

                if (null !== $validated->verdict->deliverable) {
                    $fields[] = 'validation_deliverable = :validation_deliverable';
                    $params[':validation_deliverable'] = $validated->verdict->deliverable ? 1 : 0;
                }
                if (null !== $validated->verdict->granularity) {
                    $fields[] = 'validation_granularity = :validation_granularity';
                    $params[':validation_granularity'] = $validated->verdict->granularity;
                }
                if (null !== $validated->verdict->quality) {
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

            $sql = 'UPDATE address_entity SET '.implode(', ', $fields).' WHERE id = :id AND '.$scopeWhere;
            $stmt = $this->prepare($sql);
            $ok = $stmt->execute($params);

            if (!$ok) {
                $this->pdo->rollBack();
                throw new \RuntimeException('apply_failed');
            }
            if ($stmt->rowCount() < 1) {
                $this->pdo->rollBack();
                throw new \RuntimeException('not_found');
            }

            $this->appendOutbox([
                'id' => $id,
                'ownerId' => $ownerId,
                'vendorId' => $vendorId,
                'fingerprint' => $fingerprint,
                'provider' => $validated->validationProvider,
                'validatedAt' => $validatedAt->format(DATE_ATOM),
                'deliverable' => $validated->verdict?->deliverable,
                'granularity' => $validated->verdict?->granularity,
                'quality' => $validated->verdict?->quality,
                'rawSha256' => $params[':validation_raw_sha256'] ?? null,
            ]);

            $this->pdo->commit();
        } catch (\RuntimeException $e) {
            $this->rollbackIfActive();
            throw $e;
        } catch (\Throwable) {
            $this->rollbackIfActive();
            throw new \RuntimeException('apply_failed');
        }
    }

    /** @param array<string, mixed> $payload */
    private function appendOutbox(array $payload): void
    {
        $payloadJson = $this->encodePayload($payload);
        $payloadExpr = $this->isPgsql() ? ':payload::jsonb' : ':payload';

        $stmt = $this->prepare(
            "INSERT INTO address_outbox (event_name, event_version, payload)
         VALUES (:name, :ver, {$payloadExpr})"
        );

        $stmt->execute([
            ':name' => 'AddressValidatedApplied',
            ':ver' => 1,
            ':payload' => $payloadJson,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function encodePayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            throw new \RuntimeException('payload_encode_failed');
        }

        return $json;
    }

    private function jsonAssignment(string $field, string $placeholder): string
    {
        if ($this->isPgsql()) {
            return $field.' = '.$placeholder.'::jsonb';
        }

        return $field.' = '.$placeholder;
    }

    private function tenantWhereClause(?string $ownerId, ?string $vendorId): string
    {
        if (null !== $ownerId && null !== $vendorId) {
            return '(owner_id = :owner_id AND vendor_id = :vendor_id)';
        }
        if (null !== $ownerId) {
            return '(owner_id = :owner_id)';
        }
        if (null !== $vendorId) {
            return '(vendor_id = :vendor_id)';
        }

        return '1 = 1';
    }

    /** @return array<string, string> */
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

    private function isPgsql(): bool
    {
        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return is_string($driverAttr) && 'pgsql' === $driverAttr;
    }

    private function rollbackIfActive(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function prepare(string $sql): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        if (false === $stmt) {
            throw new \RuntimeException('prepare_failed');
        }

        return $stmt;
    }
}
