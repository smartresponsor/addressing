<?php

declare(strict_types=1);

namespace App\Service\Application;

use App\Contract\Message\AddressValidated;

final readonly class AddressValidatedPayloadFactory
{
    public function hasEvidence(AddressValidated $addressValidated): bool
    {
        return null !== $addressValidated->rawInput
            || null !== $addressValidated->normalizedSnapshot
            || null !== $addressValidated->providerDigest
            || null !== $addressValidated->raw
            || $addressValidated->addressValidationVerdict instanceof \App\Contract\Message\AddressValidationVerdict;
    }

    /** @return array<string, mixed>|null */
    public function normalizedSnapshot(AddressValidated $addressValidated): ?array
    {
        if (null !== $addressValidated->normalizedSnapshot) {
            return $addressValidated->normalizedSnapshot;
        }

        $snapshot = array_filter([
            'line1Norm' => $addressValidated->line1Norm,
            'cityNorm' => $addressValidated->cityNorm,
            'regionNorm' => $addressValidated->regionNorm,
            'postalCodeNorm' => $addressValidated->postalCodeNorm,
            'latitude' => $addressValidated->latitude,
            'longitude' => $addressValidated->longitude,
            'geohash' => $addressValidated->geohash,
        ], static fn (mixed $value): bool => null !== $value);

        if ([] === $snapshot) {
            return null;
        }

        return $snapshot;
    }

    public function providerDigest(AddressValidated $addressValidated): ?string
    {
        if (null !== $addressValidated->providerDigest) {
            return $addressValidated->providerDigest;
        }

        $payload = array_filter([
            'provider' => $addressValidated->validationProvider,
            'validatedAt' => $addressValidated->validatedAt?->format(DATE_ATOM),
            'raw' => $addressValidated->raw,
            'verdict' => $addressValidated->addressValidationVerdict?->jsonSerialize(),
            'normalizedSnapshot' => $this->normalizedSnapshot($addressValidated),
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
        AddressValidated $addressValidated,
        \DateTimeImmutable $validatedAt,
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
            'provider' => $addressValidated->validationProvider,
            'validatedAt' => $validatedAt->format(DATE_ATOM),
            'deliverable' => $addressValidated->addressValidationVerdict?->deliverable,
            'granularity' => $addressValidated->addressValidationVerdict?->granularity,
            'quality' => $addressValidated->addressValidationVerdict?->quality,
            'rawSha256' => $rawSha256,
            'sourceType' => $addressValidated->sourceType,
            'providerDigest' => $providerDigest,
            'hasEvidence' => $this->hasEvidence($addressValidated),
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
            throw new \RuntimeException('payload_encode_failed');
        }

        return $json;
    }
}
