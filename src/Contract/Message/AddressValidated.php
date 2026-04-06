<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Contract\Message;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;
use Override;
use Stringable;
use Throwable;

final readonly class AddressValidated implements JsonSerializable
{
    public function __construct(
        public ?string $line1_norm,
        public ?string $city_norm,
        public ?string $region_norm,
        public ?string $postal_code_norm,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $geohash,
        public ?string $validation_provider,
        public ?DateTimeImmutable $validated_at,
        public ?string $dedupe_key,
        /** @var array<string, mixed>|null */
        public ?array $raw = null,
        public ?AddressValidationVerdict $addressValidationVerdict = null,
        public ?string $source_system = null,
        public ?string $source_type = null,
        public ?string $source_reference = null,
        public ?string $normalization_version = null,
        /** @var array<string, mixed>|null */
        public ?array $raw_input = null,
        /** @var array<string, mixed>|null */
        public ?array $normalized_snapshot = null,
        public ?string $provider_digest = null,
        public ?string $governance_status = null,
        public ?string $duplicate_of_id = null,
        public ?string $superseded_by_id = null,
        public ?string $alias_of_id = null,
        public ?string $conflict_with_id = null,
        public ?DateTimeImmutable $revalidation_due_at = null,
        public ?string $revalidation_policy = null,
        public ?string $last_validation_provider = null,
        public ?string $last_validation_status = null,
        public ?int $last_validation_score = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $line1_norm = self::asNullableString($data['line1Norm'] ?? null);
        $city_norm = self::asNullableString($data['cityNorm'] ?? null);
        $region_norm = self::asNullableString($data['regionNorm'] ?? null);
        $postal_code_norm = self::asNullableString($data['postalCodeNorm'] ?? null);
        $latitude = self::asNullableFloat($data['latitude'] ?? null);
        $longitude = self::asNullableFloat($data['longitude'] ?? null);
        $geohash = self::asNullableString($data['geohash'] ?? null);
        $validation_provider = self::asNullableString($data['validationProvider'] ?? null);
        $validated_at = self::asNullableDate($data['validatedAt'] ?? null);
        $dedupe_key = self::asNullableString($data['dedupeKey'] ?? null);

        $raw = null;
        if (array_key_exists('raw', $data) && is_array($data['raw'])) {
            /** @var array<string, mixed> $raw */
            $raw = $data['raw'];
        }

        $validation_verdict_data = null;
        if (array_key_exists('verdict', $data) && is_array($data['verdict'])) {
            /** @var array<string, mixed> $validation_verdict_data */
            $validation_verdict_data = $data['verdict'];
        } elseif (array_key_exists('validationVerdict', $data) && is_array($data['validationVerdict'])) {
            /** @var array<string, mixed> $validation_verdict_data */
            $validation_verdict_data = $data['validationVerdict'];
        }

        $verdict = AddressValidationVerdict::fromArray($validation_verdict_data);

        $source_system = self::asNullableString($data['sourceSystem'] ?? null);
        $source_type = AddressRecordPolicy::normalizeSourceType(self::asNullableString($data['sourceType'] ?? null));
        $source_reference = self::asNullableString($data['sourceReference'] ?? null);
        $normalization_version = self::asNullableString($data['normalizationVersion'] ?? null);
        $provider_digest = self::asNullableString($data['providerDigest'] ?? null);
        $governance_status = AddressRecordPolicy::normalizeGovernanceStatus(self::asNullableString($data['governanceStatus'] ?? null));
        $duplicate_of_id = self::asNullableString($data['duplicateOfId'] ?? null);
        $superseded_by_id = self::asNullableString($data['supersededById'] ?? null);
        $alias_of_id = self::asNullableString($data['aliasOfId'] ?? null);
        $conflict_with_id = self::asNullableString($data['conflictWithId'] ?? null);
        $revalidation_due_at = self::asNullableDate($data['revalidationDueAt'] ?? null);
        $revalidation_policy = AddressRecordPolicy::normalizeRevalidationPolicy(self::asNullableString($data['revalidationPolicy'] ?? null));
        $last_validation_provider = self::asNullableString($data['lastValidationProvider'] ?? null);
        $last_validation_status = AddressRecordPolicy::normalizeLastValidationStatus(self::asNullableString($data['lastValidationStatus'] ?? null));
        $last_validation_score = self::asNullableInt($data['lastValidationScore'] ?? null);

        $raw_input = null;
        if (array_key_exists('rawInput', $data) && is_array($data['rawInput'])) {
            /** @var array<string, mixed> $raw_input */
            $raw_input = $data['rawInput'];
        }

        $normalized_snapshot = null;
        if (array_key_exists('normalizedSnapshot', $data) && is_array($data['normalizedSnapshot'])) {
            /** @var array<string, mixed> $normalized_snapshot */
            $normalized_snapshot = $data['normalizedSnapshot'];
        }

        return new self(
            $line1_norm,
            $city_norm,
            $region_norm,
            $postal_code_norm,
            $latitude,
            $longitude,
            $geohash,
            $validation_provider,
            $validated_at,
            $dedupe_key,
            $raw,
            $verdict,
            $source_system,
            $source_type,
            $source_reference,
            $normalization_version,
            $raw_input,
            $normalized_snapshot,
            $provider_digest,
            $governance_status,
            $duplicate_of_id,
            $superseded_by_id,
            $alias_of_id,
            $conflict_with_id,
            $revalidation_due_at,
            $revalidation_policy,
            $last_validation_provider,
            $last_validation_status,
            $last_validation_score,
        );
    }

    public function fingerprint(): string
    {
        $serialized = $this->jsonSerialize();
        $json = json_encode($serialized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            $json = '';
        }

        return hash('sha256', $json);
    }

    /** @return array<string, mixed> */
    public function toDbArray(): array
    {
        $verdict_data = $this->addressValidationVerdict?->jsonSerialize();

        return [
            'line1_norm' => $this->line1Norm,
            'city_norm' => $this->cityNorm,
            'region_norm' => $this->regionNorm,
            'postal_code_norm' => $this->postalCodeNorm,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'geohash' => $this->geohash,
            'validation_provider' => $this->validationProvider,
            'validated_at' => $this->validatedAt?->format(DATE_ATOM),
            'dedupe_key' => $this->dedupeKey,
            'validation_raw' => $this->encodeJsonNullable($this->raw),
            'validation_verdict' => $this->encodeJsonNullable($verdict_data),
            'validation_deliverable' => $this->addressValidationVerdict?->deliverable,
            'validation_granularity' => $this->addressValidationVerdict?->granularity,
            'validation_quality' => $this->addressValidationVerdict?->quality,
            'source_system' => $this->sourceSystem,
            'source_type' => $this->sourceType,
            'source_reference' => $this->sourceReference,
            'normalization_version' => $this->normalizationVersion,
            'raw_input_snapshot' => $this->encodeJsonNullable($this->rawInput),
            'normalized_snapshot' => $this->encodeJsonNullable($this->normalizedSnapshot),
            'provider_digest' => $this->providerDigest,
            'governance_status' => $this->governanceStatus,
            'duplicate_of_id' => $this->duplicateOfId,
            'superseded_by_id' => $this->supersededById,
            'alias_of_id' => $this->aliasOfId,
            'conflict_with_id' => $this->conflictWithId,
            'revalidation_due_at' => $this->revalidationDueAt?->format(DATE_ATOM),
            'revalidation_policy' => $this->revalidationPolicy,
            'last_validation_provider' => $this->lastValidationProvider,
            'last_validation_status' => $this->lastValidationStatus,
            'last_validation_score' => $this->lastValidationScore,
        ];
    }

    /** @return array<string, mixed> */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'line1Norm' => $this->line1Norm,
            'cityNorm' => $this->cityNorm,
            'regionNorm' => $this->regionNorm,
            'postalCodeNorm' => $this->postalCodeNorm,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'geohash' => $this->geohash,
            'validationProvider' => $this->validationProvider,
            'validatedAt' => $this->validatedAt?->format(DATE_ATOM),
            'dedupeKey' => $this->dedupeKey,
            'raw' => $this->raw,
            'verdict' => $this->addressValidationVerdict?->jsonSerialize(),
            'sourceSystem' => $this->sourceSystem,
            'sourceType' => $this->sourceType,
            'sourceReference' => $this->sourceReference,
            'normalizationVersion' => $this->normalizationVersion,
            'rawInput' => $this->rawInput,
            'normalizedSnapshot' => $this->normalizedSnapshot,
            'providerDigest' => $this->providerDigest,
            'governanceStatus' => $this->governanceStatus,
            'duplicateOfId' => $this->duplicateOfId,
            'supersededById' => $this->supersededById,
            'aliasOfId' => $this->aliasOfId,
            'conflictWithId' => $this->conflictWithId,
            'revalidationDueAt' => $this->revalidationDueAt?->format(DATE_ATOM),
            'revalidationPolicy' => $this->revalidationPolicy,
            'lastValidationProvider' => $this->lastValidationProvider,
            'lastValidationStatus' => $this->lastValidationStatus,
            'lastValidationScore' => $this->lastValidationScore,
        ];
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function encodeJsonNullable(?array $data): ?string
    {
        if (null === $data) {
            return null;
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            return null;
        }

        return $json;
    }

    private static function asNullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        if (is_string($value)) {
            $string_value = trim($value);

            return '' === $string_value ? null : $string_value;
        }
        if (is_int($value) || is_float($value) || is_bool($value) || $value instanceof Stringable) {
            $string_value = trim((string) $value);

            return '' === $string_value ? null : $string_value;
        }

        return null;
    }

    private static function asNullableFloat(mixed $value): ?float
    {
        if (null === $value || '' === $value) {
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

    private static function asNullableInt(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value)) {
            $string_value = trim($value);
            if ('' === $string_value) {
                return null;
            }
            if (1 === preg_match('/^-?\d+$/', $string_value)) {
                return (int) $string_value;
            }
        }

        return null;
    }

    private static function asNullableDate(mixed $value): ?DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }
        try {
            if ($value instanceof DateTimeInterface) {
                return DateTimeImmutable::createFromInterface($value);
            }
            if (is_string($value)) {
                return new DateTimeImmutable($value);
            }
            if (is_int($value)) {
                return new DateTimeImmutable('@'.$value);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
