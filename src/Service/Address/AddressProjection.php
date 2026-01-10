<?php
declare(strict_types=1);

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

namespace App\Service\Address;

use App\EntityInterface\Address\AddressInterface;
use App\ServiceInterface\Address\AddressProjectionInterface;
use PDO;

/**
 *
 */

/**
 *
 */
final class AddressProjection implements AddressProjectionInterface
{
    /**
     * @param \PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param \App\EntityInterface\Address\AddressInterface $a
     * @return void
     */
    public function upsert(AddressInterface $a): void
    {
        $sql = <<<'SQL'
INSERT INTO address_projection
    (id, owner_id, vendor_id, line1, line2, city, region, postal_code, country_code,
     line1_norm, city_norm, region_norm, postal_code_norm,
     latitude, longitude, geohash,
     validation_status, validation_provider, validated_at, dedupe_key,
     validation_fingerprint, validation_deliverable, validation_granularity, validation_quality,
     validation_raw, validation_verdict,
     created_at, updated_at, deleted_at)
VALUES
    (:id, :owner_id, :vendor_id, :line1, :line2, :city, :region, :postal_code, :country_code,
     :line1_norm, :city_norm, :region_norm, :postal_code_norm,
     :latitude, :longitude, :geohash,
     :validation_status, :validation_provider, :validated_at, :dedupe_key,
     :validation_fingerprint, :validation_deliverable, :validation_granularity, :validation_quality,
     :validation_raw, :validation_verdict,
     :created_at, :updated_at, :deleted_at)
ON DUPLICATE KEY UPDATE
    owner_id=VALUES(owner_id),
    vendor_id=VALUES(vendor_id),
    line1=VALUES(line1),
    line2=VALUES(line2),
    city=VALUES(city),
    region=VALUES(region),
    postal_code=VALUES(postal_code),
    country_code=VALUES(country_code),
    line1_norm=VALUES(line1_norm),
    city_norm=VALUES(city_norm),
    region_norm=VALUES(region_norm),
    postal_code_norm=VALUES(postal_code_norm),
    latitude=VALUES(latitude),
    longitude=VALUES(longitude),
    geohash=VALUES(geohash),
    validation_status=VALUES(validation_status),
    validation_provider=VALUES(validation_provider),
    validated_at=VALUES(validated_at),
    dedupe_key=VALUES(dedupe_key),
    validation_fingerprint=VALUES(validation_fingerprint),
    validation_deliverable=VALUES(validation_deliverable),
    validation_granularity=VALUES(validation_granularity),
    validation_quality=VALUES(validation_quality),
    validation_raw=VALUES(validation_raw),
    validation_verdict=VALUES(validation_verdict),
    created_at=VALUES(created_at),
    updated_at=VALUES(updated_at),
    deleted_at=VALUES(deleted_at)
SQL;

        $stmt = $this->pdo->prepare($sql);
        $raw = $a->validationRaw();
        $verdict = $a->validationVerdict();
        $deliverable = $a->validationDeliverable();

        $stmt->execute([
            ':id' => $a->id(),
            ':owner_id' => $a->ownerId(),
            ':vendor_id' => $a->vendorId(),
            ':line1' => $a->line1(),
            ':line2' => $a->line2(),
            ':city' => $a->city(),
            ':region' => $a->region(),
            ':postal_code' => $a->postalCode(),
            ':country_code' => $a->countryCode(),
            ':line1_norm' => $a->line1Norm(),
            ':city_norm' => $a->cityNorm(),
            ':region_norm' => $a->regionNorm(),
            ':postal_code_norm' => $a->postalCodeNorm(),
            ':latitude' => $a->latitude(),
            ':longitude' => $a->longitude(),
            ':geohash' => $a->geohash(),
            ':validation_status' => $a->validationStatus(),
            ':validation_provider' => $a->validationProvider(),
            ':validated_at' => $a->validatedAt(),
            ':dedupe_key' => $a->dedupeKey(),
            ':validation_fingerprint' => $a->validationFingerprint(),
            ':validation_deliverable' => $deliverable === null ? null : (int)$deliverable,
            ':validation_granularity' => $a->validationGranularity(),
            ':validation_quality' => $a->validationQuality(),
            ':validation_raw' => $raw === null ? null : json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':validation_verdict' => $verdict === null ? null : json_encode($verdict, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':created_at' => $a->createdAt(),
            ':updated_at' => $a->updatedAt(),
            ':deleted_at' => $a->deletedAt(),
        ]);
    }
}
