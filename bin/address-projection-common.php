<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

use App\Entity\Record\AddressRecord;
use App\Service\Application\AddressProjectionService;

require_once __DIR__.'/../vendor/autoload.php';

function projection_pdo(string $prefix): PDO
{
    return new PDO(
        (string) getenv($prefix.'_DSN'),
        (string) getenv($prefix.'_USER'),
        (string) getenv($prefix.'_PASS'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

function projection_svc(): AddressProjectionService
{
    return new AddressProjectionService(projection_pdo('MY'));
}

/** @return array<string, mixed>|null */
function decode_json_opt(mixed $value): ?array
{
    if (!is_string($value) || '' === $value) {
        return null;
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : null;
}

/** @param array<string, mixed> $row */
function bool_opt(array $row, string $key): ?bool
{
    if (!array_key_exists($key, $row) || null === $row[$key]) {
        return null;
    }

    $value = $row[$key];
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value)) {
        return 1 === $value;
    }
    if (is_string($value)) {
        return in_array(strtolower(trim($value)), ['1', 't', 'true', 'yes'], true);
    }

    return null;
}

/** @param array<string, mixed> $row */
function row_address(array $row): AddressRecord
{
    $validationRaw = decode_json_opt($row['validation_raw'] ?? null);
    $validationVerdict = decode_json_opt($row['validation_verdict'] ?? null);
    $validationDeliverable = bool_opt($row, 'validation_deliverable');

    return new AddressRecord(
        (string) $row['id'],
        isset($row['owner_id']) ? (string) $row['owner_id'] : null,
        isset($row['vendor_id']) ? (string) $row['vendor_id'] : null,
        (string) $row['line1'],
        isset($row['line2']) ? (string) $row['line2'] : null,
        (string) $row['city'],
        isset($row['region']) ? (string) $row['region'] : null,
        isset($row['postal_code']) ? (string) $row['postal_code'] : null,
        (string) $row['country_code'],
        isset($row['line1_norm']) ? (string) $row['line1_norm'] : null,
        isset($row['city_norm']) ? (string) $row['city_norm'] : null,
        isset($row['region_norm']) ? (string) $row['region_norm'] : null,
        isset($row['postal_code_norm']) ? (string) $row['postal_code_norm'] : null,
        isset($row['latitude']) ? (float) $row['latitude'] : null,
        isset($row['longitude']) ? (float) $row['longitude'] : null,
        isset($row['geohash']) ? (string) $row['geohash'] : null,
        (string) $row['validation_status'],
        isset($row['validation_provider']) ? (string) $row['validation_provider'] : null,
        isset($row['validated_at']) ? (string) $row['validated_at'] : null,
        isset($row['dedupe_key']) ? (string) $row['dedupe_key'] : null,
        (string) $row['created_at'],
        isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        isset($row['deleted_at']) ? (string) $row['deleted_at'] : null,
        isset($row['validation_fingerprint']) ? (string) $row['validation_fingerprint'] : null,
        $validationRaw,
        $validationVerdict,
        $validationDeliverable,
        isset($row['validation_granularity']) ? (string) $row['validation_granularity'] : null,
        isset($row['validation_quality']) ? (int) $row['validation_quality'] : null,
    );
}
