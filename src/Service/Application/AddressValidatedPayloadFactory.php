<?php

declare(strict_types=1);

namespace App\Service\Application;

use App\Contract\Message\AddressValidated;
use App\Contract\Message\AddressValidationVerdict;
use DateTimeImmutable;
use RuntimeException;
use function array_filter;
use function hash;
use function is_string;
use function json_encode;
use function trim;

/** Builds normalized payload fragments from address validation messages. */
final readonly class AddressValidatedPayloadFactory
{
    public function hasEvidence(AddressValidated $address_validated): bool
    {
        return null !== $address_validated->rawInput
            || null !== $address_validated->normalizedSnapshot
            || null !== $address_validated->providerDigest
            || null !== $address_validated->raw
            || $address_validated->addressValidationVerdict instanceof AddressValidationVerdict;
    }

    /** @return array<string, mixed>|null */
    public function normalizedSnapshot(AddressValidated $address_validated): ?array
    {
        if (null !== $address_validated->normalizedSnapshot) {
            return $address_validated->normalizedSnapshot;
        }

        $snapshot = array_filter([
            'line1Norm' => $address_validated->line1Norm,
            'cityNorm' => $address_validated->cityNorm,
            'regionNorm' => $address_validated->regionNorm,
            'postalCodeNorm' => $address_validated->postalCodeNorm,
            'latitude' => $address_validated->latitude,
            'longitude' => $address_validated->longitude,
            'geohash' => $address_validated->geohash,
        ], static fn (mixed $value): bool => null !== $value);

        if ([] === $snapshot) {
            return null;
        }

        return $snapshot;
    }

    public function providerDigest(AddressValidated $address_validated): ?string
    {
        if (null !== $address_validated->providerDigest) {
            return $address_validated->providerDigest;
        }

        $payload = array_filter([
            'provider' => $address_validated->validationProvider,
            'validatedAt' => $address_validated->validatedAt?->format(DATE_ATOM),
            'raw' => $address_validated->raw,
            'verdict' => $address_validated->addressValidationVerdict?->jsonSerialize(),
            'normalizedSnapshot' => $this->normalizedSnapshot($address_validated),
        ], static fn (mixed $value): bool => null !== $value);

        if ([] === $payload) {
            return null;
        }

        return hash('sha256', $this->encodePayload($payload));
    }

    public function sanitizeGovernanceLink(?string $linkId, string $currentId): ?string
    {
        $linkId = is_string($linkId) ? trim($linkId) : '';
        if ('' === $linkId || $linkId === $currentId) {
            return null;
        }

        return $linkId;
    }

    public function governanceLinkId(
        string $governanceStatus,
        ?string $duplicateOfId,
        ?string $supersededById,
        ?string $aliasOfId,
        ?string $conflictWithId,
    ): ?string {
        return match ($governanceStatus) {
            'duplicate' => $duplicateOfId,
            'superseded' => $supersededById,
            'alias' => $aliasOfId,
            'conflict' => $conflictWithId,
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public function outboxPayload(
        string $id,
        ?string $ownerId,
        ?string $vendorId,
        string $fingerprint,
        AddressValidated $address_validated,
        DateTimeImmutable $validatedAt,
        ?string $rawSha256,
        string $governanceStatus,
        ?string $duplicateOfId,
        ?string $supersededById,
        ?string $aliasOfId,
        ?string $conflictWithId,
        ?string $revalidationDueAt,
        ?string $revalidationPolicy,
        string $lastValidationStatus,
        ?int $lastValidationScore,
        ?string $evidenceSnapshotId,
        ?string $providerDigest,
    ): array {
        return [
            'id' => $id,
            'ownerId' => $ownerId,
            'vendorId' => $vendorId,
            'fingerprint' => $fingerprint,
            'provider' => $address_validated->validationProvider,
            'validatedAt' => $validatedAt->format(DATE_ATOM),
            'deliverable' => $address_validated->addressValidationVerdict?->deliverable,
            'granularity' => $address_validated->addressValidationVerdict?->granularity,
            'quality' => $address_validated->addressValidationVerdict?->quality,
            'rawSha256' => $rawSha256,
            'sourceType' => $address_validated->sourceType,
            'providerDigest' => $providerDigest,
            'hasEvidence' => $this->hasEvidence($address_validated),
            'governanceStatus' => $governanceStatus,
            'governanceLinkId' => $this->governanceLinkId($governanceStatus, $duplicateOfId, $supersededById, $aliasOfId, $conflictWithId),
            'revalidationDueAt' => $revalidationDueAt,
            'revalidationPolicy' => $revalidationPolicy,
            'lastValidationStatus' => $lastValidationStatus,
            'lastValidationScore' => $lastValidationScore,
            'evidenceSnapshotId' => $evidenceSnapshotId,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function encodePayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            throw new RuntimeException('payload_encode_failed');
        }

        return $json;
    }
}
