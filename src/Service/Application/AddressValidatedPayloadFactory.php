<?php

declare(strict_types=1);

namespace App\Service\Application;

use App\Contract\Message\AddressValidated;
use App\Contract\Message\AddressValidationVerdict;

/** Builds normalized payload fragments from address validation messages. */
final readonly class AddressValidatedPayloadFactory
{
    public function hasEvidence(AddressValidated $addressValidated): bool
    {
        return null !== $addressValidated->rawInput
            || null !== $addressValidated->normalizedSnapshot
            || null !== $addressValidated->providerDigest
            || null !== $addressValidated->raw
            || $addressValidated->addressValidationVerdict instanceof AddressValidationVerdict;
    }

    /** @return array<string, mixed>|null */
    public function normalizedSnapshot(AddressValidated $addressValidated): ?array
    {
        if (null !== $addressValidated->normalizedSnapshot) {
            return $addressValidated->normalizedSnapshot;
        }

        $snapshot = \array_filter([
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

        $payload = \array_filter([
            'provider' => $addressValidated->validationProvider,
            'validatedAt' => $addressValidated->validatedAt?->format(DATE_ATOM),
            'raw' => $addressValidated->raw,
            'verdict' => $addressValidated->addressValidationVerdict?->jsonSerialize(),
            'normalizedSnapshot' => $this->normalizedSnapshot($addressValidated),
        ], static fn (mixed $value): bool => null !== $value);

        if ([] === $payload) {
            return null;
        }

        return \hash('sha256', $this->encodePayload($payload));
    }

    public function sanitizeGovernanceLink(?string $linkId, string $currentId): ?string
    {
        $linkId = \is_string($linkId) ? \trim($linkId) : '';
        if ('' === $linkId || $linkId === $currentId) {
            return null;
        }

        return $linkId;
    }

    /** @return array<string, mixed> */
    public function outboxPayload(
        AddressValidatedOutboxContext $context,
        AddressValidated $addressValidated,
    ): array {
        return [
            'id' => $context->id,
            'ownerId' => $context->ownerId,
            'vendorId' => $context->vendorId,
            'fingerprint' => $context->fingerprint,
            'provider' => $addressValidated->validationProvider,
            'validatedAt' => $context->validatedAt->format(DATE_ATOM),
            'deliverable' => $addressValidated->addressValidationVerdict?->deliverable,
            'granularity' => $addressValidated->addressValidationVerdict?->granularity,
            'quality' => $addressValidated->addressValidationVerdict?->quality,
            'rawSha256' => $context->rawSha256,
            'sourceType' => $addressValidated->sourceType,
            'providerDigest' => $context->providerDigest,
            'hasEvidence' => $this->hasEvidence($addressValidated),
            'governanceStatus' => $context->governanceStatus,
            'governanceLinkId' => $this->governanceLinkId($context),
            'revalidationDueAt' => $context->revalidationDueAt,
            'revalidationPolicy' => $context->revalidationPolicy,
            'lastValidationStatus' => $context->lastValidationStatus,
            'lastValidationScore' => $context->lastValidationScore,
            'evidenceSnapshotId' => $context->evidenceSnapshotId,
        ];
    }

    private function governanceLinkId(AddressValidatedOutboxContext $context): ?string
    {
        return match ($context->governanceStatus) {
            'duplicate' => $context->duplicateOfId,
            'superseded' => $context->supersededById,
            'alias' => $context->aliasOfId,
            'conflict' => $context->conflictWithId,
            default => null,
        };
    }

    /** @param array<string, mixed> $payload */
    private function encodePayload(array $payload): string
    {
        $json = \json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            throw new \RuntimeException('payload_encode_failed');
        }

        return $json;
    }
}
