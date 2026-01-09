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
use DateTimeImmutable;
use JsonException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class AddressValidatedApplier implements AddressValidatedApplierInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    /**
     * Applies a validated address snapshot.
     *
     * Absolute guarantees:
     * - idempotent by validation_fingerprint
     * - row-level isolation via SELECT ... FOR UPDATE
     * - atomic state + outbox write
     * - no partial updates
     *
     * @throws RuntimeException when the operation cannot be completed
     */
    public function apply(string $id, AddressValidated $validated): void
    {
        $now = new DateTimeImmutable();
        $validatedAt = $validated->validatedAt ?? $now;
        $fingerprint = $validated->fingerprint();

        try {
            $this->pdo->beginTransaction();

            $row = $this->lockAddressRow($id);

            if ($this->isAlreadyApplied($row['validation_fingerprint'] ?? null, $fingerprint)) {
                $this->pdo->commit();
                return;
            }

            $this->updateAddressEntity(
                $id,
                $validated,
                $fingerprint,
                $now,
                $validatedAt
            );

            $this->appendOutboxEvent(
                $id,
                $validated,
                $fingerprint,
                $validatedAt
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // The applier must fail atomically.
            throw new RuntimeException('apply_failed', 0, $e);
        }
    }

    /**
     * Locks the address row for update.
     */
    private function lockAddressRow(string $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT validation_fingerprint FROM address_entity WHERE id = :id FOR UPDATE'
        );
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException('not_found');
        }

        return $row;
    }

    /**
     * Idempotency guard based on validation fingerprint.
     */
    private function isAlreadyApplied(?string $previous, string $current): bool
    {
        return is_string($previous)
            && $previous !== ''
            && hash_equals($previous, $current);
    }

    /**
     * Updates the address_entity record with validated data.
     */
    private function updateAddressEntity(
        string $id,
        AddressValidated $validated,
        string $fingerprint,
        DateTimeImmutable $now,
        DateTimeImmutable $validatedAt
    ): void {
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

        $this->bindOptional($fields, $params, 'line1_norm', $validated->line1Norm);
        $this->bindOptional($fields, $params, 'city_norm', $validated->cityNorm);
        $this->bindOptional($fields, $params, 'region_norm', $validated->regionNorm);
        $this->bindOptional($fields, $params, 'postal_code_norm', $validated->postalCodeNorm);
        $this->bindOptional($fields, $params, 'latitude', $validated->latitude);
        $this->bindOptional($fields, $params, 'longitude', $validated->longitude);
        $this->bindOptional($fields, $params, 'geohash', $validated->geohash);

        if ($validated->raw !== null) {
            $raw = $this->encodeJson($validated->raw);
            $fields[] = 'validation_raw = :validation_raw::jsonb';
            $fields[] = 'validation_raw_sha256 = :validation_raw_sha256';
            $params[':validation_raw'] = $raw;
            $params[':validation_raw_sha256'] = hash('sha256', $raw);
        }

        if ($validated->verdict !== null) {
            $fields[] = 'validation_verdict = :validation_verdict::jsonb';
            $params[':validation_verdict'] = $this->encodeJson(
                $validated->verdict->jsonSerialize()
            );

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

        if (!$stmt->execute($params) || $stmt->rowCount() < 1) {
            throw new RuntimeException('apply_failed');
        }
    }

    /**
     * Appends an outbox event in the same transaction.
     */
    private function appendOutboxEvent(
        string $id,
        AddressValidated $validated,
        string $fingerprint,
        DateTimeImmutable $validatedAt
    ): void {
        $payload = [
            'id' => $id,
            'fingerprint' => $fingerprint,
            'provider' => $validated->validationProvider,
            'validatedAt' => $validatedAt->format(DATE_ATOM),
            'deliverable' => $validated->verdict?->deliverable,
            'granularity' => $validated->verdict?->granularity,
            'quality' => $validated->verdict?->quality,
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO address_outbox(event_name, event_version, payload)
             VALUES (:name, :ver, :payload::jsonb)'
        );

        $stmt->execute([
            ':name' => 'AddressValidatedApplied',
            ':ver' => 1,
            ':payload' => $this->encodeJson($payload),
        ]);
    }

    /**
     * Binds optional scalar fields safely.
     */
    private function bindOptional(array &$fields, array &$params, string $column, mixed $value): void
    {
        if ($value !== null) {
            $fields[] = $column . ' = :' . $column;
            $params[':' . $column] = $value;
        }
    }

    /**
     * Encodes JSON with a hard failure contract.
     */
    private function encodeJson(mixed $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            // JSON corruption is not recoverable at this layer.
            throw new RuntimeException('json_encode_failed');
        }
    }
}
