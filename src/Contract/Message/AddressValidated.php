<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Contract\Message;

final readonly class AddressValidated implements \JsonSerializable
{
    public function __construct(
        public ?string $line1Norm,
        public ?string $cityNorm,
        public ?string $regionNorm,
        public ?string $postalCodeNorm,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $geohash,
        public ?string $validationProvider,
        public ?\DateTimeImmutable $validatedAt,
        public ?string $dedupeKey,
        /** @var array<string, mixed>|null */
        public ?array $raw = null,
        public ?AddressValidationVerdict $addressValidationVerdict = null,
        public ?string $sourceSystem = null,
        public ?string $sourceType = null,
        public ?string $sourceReference = null,
        public ?string $normalizationVersion = null,
        /** @var array<string, mixed>|null */
        public ?array $rawInput = null,
        /** @var array<string, mixed>|null */
        public ?array $normalizedSnapshot = null,
        public ?string $providerDigest = null,
        public ?string $governanceStatus = null,
        public ?string $duplicateOfId = null,
        public ?string $supersededById = null,
        public ?string $aliasOfId = null,
        public ?string $conflictWithId = null,
        public ?\DateTimeImmutable $revalidationDueAt = null,
        public ?string $revalidationPolicy = null,
        public ?string $lastValidationProvider = null,
        public ?string $lastValidationStatus = null,
        public ?int $lastValidationScore = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $line1Norm = self::asNullableString($data['line1Norm'] ?? null);
        $cityNorm = self::asNullableString($data['cityNorm'] ?? null);
        $regionNorm = self::asNullableString($data['regionNorm'] ?? null);
        $postalCodeNorm = self::asNullableString($data['postalCodeNorm'] ?? null);
        $latitude = self::asNullableFloat($data['latitude'] ?? null);
        $longitude = self::asNullableFloat($data['longitude'] ?? null);
        $geohash = self::asNullableString($data['geohash'] ?? null);
        $validationProvider = self::asNullableString($data['validationProvider'] ?? null);
        $validatedAt = self::asNullableDate($data['validatedAt'] ?? null);
        $dedupeKey = self::asNullableString($data['dedupeKey'] ?? null);

        $raw = null;
        if (array_key_exists('raw', $data) && is_array($data['raw'])) {
            /** @var array<string, mixed> $raw */
            $raw = $data['raw'];
        }

        $verdictArr = null;
        if (array_key_exists('verdict', $data) && is_array($data['verdict'])) {
            /** @var array<string, mixed> $verdictArr */
            $verdictArr = $data['verdict'];
        } elseif (array_key_exists('validationVerdict', $data) && is_array($data['validationVerdict'])) {
            /** @var array<string, mixed> $verdictArr */
            $verdictArr = $data['validationVerdict'];
        }

        $verdict = AddressValidationVerdict::fromArray($verdictArr);

        $sourceSystem = self::asNullableString($data['sourceSystem'] ?? null);
        $sourceType = AddressRecordPolicy::normalizeSourceType(self::asNullableString($data['sourceType'] ?? null));
        $sourceReference = self::asNullableString($data['sourceReference'] ?? null);
        $normalizationVersion = self::asNullableString($data['normalizationVersion'] ?? null);
        $providerDigest = self::asNullableString($data['providerDigest'] ?? null);
        $governanceStatus = AddressRecordPolicy::normalizeGovernanceStatus(self::asNullableString($data['governanceStatus'] ?? null));
        $duplicateOfId = self::asNullableString($data['duplicateOfId'] ?? null);
        $supersededById = self::asNullableString($data['supersededById'] ?? null);
        $aliasOfId = self::asNullableString($data['aliasOfId'] ?? null);
        $conflictWithId = self::asNullableString($data['conflictWithId'] ?? null);
        $revalidationDueAt = self::asNullableDate($data['revalidationDueAt'] ?? null);
        $revalidationPolicy = AddressRecordPolicy::normalizeRevalidationPolicy(self::asNullableString($data['revalidationPolicy'] ?? null));
        $lastValidationProvider = self::asNullableString($data['lastValidationProvider'] ?? null);
        $lastValidationStatus = AddressRecordPolicy::normalizeLastValidationStatus(self::asNullableString($data['lastValidationStatus'] ?? null));
        $lastValidationScore = self::asNullableInt($data['lastValidationScore'] ?? null);

        $rawInput = null;
        if (array_key_exists('rawInput', $data) && is_array($data['rawInput'])) {
            /** @var array<string, mixed> $rawInput */
            $rawInput = $data['rawInput'];
        }

        $normalizedSnapshot = null;
        if (array_key_exists('normalizedSnapshot', $data) && is_array($data['normalizedSnapshot'])) {
            /** @var array<string, mixed> $normalizedSnapshot */
            $normalizedSnapshot = $data['normalizedSnapshot'];
        }

        return new self(
            $line1Norm,
            $cityNorm,
            $regionNorm,
            $postalCodeNorm,
            $latitude,
            $longitude,
            $geohash,
            $validationProvider,
            $validatedAt,
            $dedupeKey,
            $raw,
            $verdict,
            $sourceSystem,
            $sourceType,
            $sourceReference,
            $normalizationVersion,
            $rawInput,
            $normalizedSnapshot,
            $providerDigest,
            $governanceStatus,
            $duplicateOfId,
            $supersededById,
            $aliasOfId,
            $conflictWithId,
            $revalidationDueAt,
            $revalidationPolicy,
            $lastValidationProvider,
            $lastValidationStatus,
            $lastValidationScore,
        );
    }

    public function fingerprint(): string
    {
        $arr = $this->jsonSerialize();
        $json = json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            $json = '';
        }

        return hash('sha256', $json);
    }

    /** @return array<string, mixed> */
    public function toDbArray(): array
    {
        $verdictArr = $this->addressValidationVerdict?->jsonSerialize();

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
            'validation_verdict' => $this->encodeJsonNullable($verdictArr),
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
    #[\Override]
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

    private static function asNullableString(mixed $v): ?string
    {
        if (null === $v) {
            return null;
        }
        if (is_string($v)) {
            $s = trim($v);

            return '' === $s ? null : $s;
        }
        if (is_int($v) || is_float($v) || is_bool($v) || $v instanceof \Stringable) {
            $s = trim((string) $v);

            return '' === $s ? null : $s;
        }

        return null;
    }

    private static function asNullableFloat(mixed $v): ?float
    {
        if (null === $v || '' === $v) {
            return null;
        }
        if (is_float($v) || is_int($v)) {
            return (float) $v;
        }
        if (is_string($v) && is_numeric($v)) {
            return (float) $v;
        }

        return null;
    }

    private static function asNullableInt(mixed $v): ?int
    {
        if (null === $v || '' === $v) {
            return null;
        }
        if (is_int($v)) {
            return $v;
        }
        if (is_float($v)) {
            return (int) $v;
        }
        if (is_string($v)) {
            $s = trim($v);
            if ('' === $s) {
                return null;
            }
            if (1 === preg_match('/^-?\d+$/', $s)) {
                return (int) $s;
            }
        }

        return null;
    }

    private static function asNullableDate(mixed $v): ?\DateTimeImmutable
    {
        if (null === $v || '' === $v) {
            return null;
        }
        try {
            if ($v instanceof \DateTimeInterface) {
                return \DateTimeImmutable::createFromInterface($v);
            }
            if (is_string($v)) {
                return new \DateTimeImmutable($v);
            }
            if (is_int($v)) {
                return new \DateTimeImmutable('@'.$v);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
