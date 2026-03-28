<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);


namespace App\Service\Application;

use App\EntityInterface\Record\AddressInterface;
use App\ServiceInterface\Application\AddressProjectionServiceInterface;

final readonly class AddressProjectionService implements AddressProjectionServiceInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    #[\Override]
    public function upsert(AddressInterface $address): void
    {
        $sql = <<<'SQL'
INSERT INTO address_projection
    (id, owner_id, vendor_id, line1, line2, city, region, postal_code, country_code,
     line1_norm, city_norm, region_norm, postal_code_norm,
     latitude, longitude, geohash,
     validation_status, validation_provider, validated_at, dedupe_key,
     validation_fingerprint, validation_deliverable, validation_granularity, validation_quality,
     validation_raw, validation_verdict,
     source_system, source_type, source_reference, normalization_version, raw_input_snapshot, normalized_snapshot, provider_digest,
     governance_status, duplicate_of_id, superseded_by_id, alias_of_id, conflict_with_id,
     revalidation_due_at, revalidation_policy, last_validation_provider, last_validation_status, last_validation_score,
     created_at, updated_at, deleted_at)
VALUES
    (:id, :owner_id, :vendor_id, :line1, :line2, :city, :region, :postal_code, :country_code,
     :line1_norm, :city_norm, :region_norm, :postal_code_norm,
     :latitude, :longitude, :geohash,
     :validation_status, :validation_provider, :validated_at, :dedupe_key,
     :validation_fingerprint, :validation_deliverable, :validation_granularity, :validation_quality,
     :validation_raw, :validation_verdict,
     :source_system, :source_type, :source_reference, :normalization_version, :raw_input_snapshot, :normalized_snapshot, :provider_digest,
     :governance_status, :duplicate_of_id, :superseded_by_id, :alias_of_id, :conflict_with_id,
     :revalidation_due_at, :revalidation_policy, :last_validation_provider, :last_validation_status, :last_validation_score,
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
    source_system=VALUES(source_system),
    source_type=VALUES(source_type),
    source_reference=VALUES(source_reference),
    normalization_version=VALUES(normalization_version),
    raw_input_snapshot=VALUES(raw_input_snapshot),
    normalized_snapshot=VALUES(normalized_snapshot),
    provider_digest=VALUES(provider_digest),
    governance_status=VALUES(governance_status),
    duplicate_of_id=VALUES(duplicate_of_id),
    superseded_by_id=VALUES(superseded_by_id),
    alias_of_id=VALUES(alias_of_id),
    conflict_with_id=VALUES(conflict_with_id),
    revalidation_due_at=VALUES(revalidation_due_at),
    revalidation_policy=VALUES(revalidation_policy),
    last_validation_provider=VALUES(last_validation_provider),
    last_validation_status=VALUES(last_validation_status),
    last_validation_score=VALUES(last_validation_score),
    created_at=VALUES(created_at),
    updated_at=VALUES(updated_at),
    deleted_at=VALUES(deleted_at)
SQL;

        $stmt = $this->pdo->prepare($sql);
        $raw = $address->validationRaw();
        $verdict = $address->validationVerdict();
        $deliverable = $address->validationDeliverable();

        $stmt->execute([
            ':id' => $address->id(),
            ':owner_id' => $address->ownerId(),
            ':vendor_id' => $address->vendorId(),
            ':line1' => $address->line1(),
            ':line2' => $address->line2(),
            ':city' => $address->city(),
            ':region' => $address->region(),
            ':postal_code' => $address->postalCode(),
            ':country_code' => $address->countryCode(),
            ':line1_norm' => $address->line1Norm(),
            ':city_norm' => $address->cityNorm(),
            ':region_norm' => $address->regionNorm(),
            ':postal_code_norm' => $address->postalCodeNorm(),
            ':latitude' => $address->latitude(),
            ':longitude' => $address->longitude(),
            ':geohash' => $address->geohash(),
            ':validation_status' => $address->validationStatus(),
            ':validation_provider' => $address->validationProvider(),
            ':validated_at' => $address->validatedAt(),
            ':dedupe_key' => $address->dedupeKey(),
            ':validation_fingerprint' => $address->validationFingerprint(),
            ':validation_deliverable' => null === $deliverable ? null : (int) $deliverable,
            ':validation_granularity' => $address->validationGranularity(),
            ':validation_quality' => $address->validationQuality(),
            ':validation_raw' => null === $raw ? null : json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':validation_verdict' => null === $verdict ? null : json_encode($verdict, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':source_system' => $address->sourceSystem(),
            ':source_type' => $address->sourceType(),
            ':source_reference' => $address->sourceReference(),
            ':normalization_version' => $address->normalizationVersion(),
            ':raw_input_snapshot' => ($v = $address->rawInputSnapshot()) === null ? null : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':normalized_snapshot' => ($v = $address->normalizedSnapshot()) === null ? null : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':provider_digest' => $address->providerDigest(),
            ':governance_status' => $address->governanceStatus(),
            ':duplicate_of_id' => $address->duplicateOfId(),
            ':superseded_by_id' => $address->supersededById(),
            ':alias_of_id' => $address->aliasOfId(),
            ':conflict_with_id' => $address->conflictWithId(),
            ':revalidation_due_at' => $address->revalidationDueAt(),
            ':revalidation_policy' => $address->revalidationPolicy(),
            ':last_validation_provider' => $address->lastValidationProvider(),
            ':last_validation_status' => $address->lastValidationStatus(),
            ':last_validation_score' => $address->lastValidationScore(),
            ':created_at' => $address->createdAt(),
            ':updated_at' => $address->updatedAt(),
            ':deleted_at' => $address->deletedAt(),
        ]);
    }
}
