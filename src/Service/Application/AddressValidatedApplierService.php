<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Application;

use App\Contract\Message\AddressOutboxEventMessage;
use App\Contract\Message\AddressValidated;
use App\Entity\AddressEntity;
use App\Entity\AddressEvidenceSnapshotEntity;
use App\Entity\AddressOutboxEntity;
use App\ServiceInterface\Application\AddressValidatedApplierServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

final class AddressValidatedApplierService implements AddressValidatedApplierServiceInterface
{
    private AddressValidatedPayloadFactory $payloadFactory;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        ?AddressValidatedPayloadFactory $payloadFactory = null,
    ) {
        $this->payloadFactory = $payloadFactory ?? new AddressValidatedPayloadFactory();
    }

    #[\Override]
    public function apply(string $id, AddressValidated $addressValidated, ?string $ownerId = null, ?string $vendorId = null): void
    {
        $fingerprint = $addressValidated->fingerprint();
        $now = new \DateTimeImmutable('now');
        $validatedAt = $addressValidated->validatedAt ?? $now;
        $entity = $this->findAddressEntity($id, $ownerId, $vendorId);
        if (!$entity instanceof AddressEntity) {
            throw new \RuntimeException('not_found');
        }

        $this->entityManager->beginTransaction();
        try {
            if ($entity->getValidationFingerprint() === $fingerprint) {
                $this->entityManager->commit();

                return;
            }

            $normalizedSnapshot = $this->payloadFactory->normalizedSnapshot($addressValidated);
            $providerDigest = $this->payloadFactory->providerDigest($addressValidated);
            $validationIssues = $this->validationIssues($addressValidated);
            $rawSha256 = null;
            if (null !== $addressValidated->raw) {
                $rawSha256 = hash('sha256', $this->encodePayload($addressValidated->raw));
            }

            $entity
                ->setLine1Norm($addressValidated->line1Norm)
                ->setCityNorm($addressValidated->cityNorm)
                ->setRegionNorm($addressValidated->regionNorm)
                ->setPostalCodeNorm($addressValidated->postalCodeNorm)
                ->setLatitude($addressValidated->latitude)
                ->setLongitude($addressValidated->longitude)
                ->setGeohash($addressValidated->geohash)
                ->setValidationProvider($addressValidated->validationProvider)
                ->setValidationStatus('validated')
                ->setValidatedAt($validatedAt)
                ->setDedupeKey($addressValidated->dedupeKey)
                ->setValidationFingerprint($fingerprint)
                ->setUpdatedAt($now)
                ->setSourceSystem($addressValidated->sourceSystem)
                ->setSourceType($addressValidated->sourceType)
                ->setSourceReference($addressValidated->sourceReference)
                ->setNormalizationVersion($addressValidated->normalizationVersion)
                ->setRawInputSnapshot($addressValidated->rawInput)
                ->setNormalizedSnapshot($normalizedSnapshot)
                ->setProviderDigest($providerDigest)
                ->setGovernanceStatus($this->normalizeGovernanceStatus($addressValidated->governanceStatus))
                ->setDuplicateOfId($this->sanitizeGovernanceLink($addressValidated->duplicateOfId, $id))
                ->setSupersededById($this->sanitizeGovernanceLink($addressValidated->supersededById, $id))
                ->setAliasOfId($this->sanitizeGovernanceLink($addressValidated->aliasOfId, $id))
                ->setConflictWithId($this->sanitizeGovernanceLink($addressValidated->conflictWithId, $id))
                ->setRevalidationDueAt($addressValidated->revalidationDueAt)
                ->setRevalidationPolicy($addressValidated->revalidationPolicy)
                ->setLastValidationProvider($addressValidated->lastValidationProvider ?? $addressValidated->validationProvider)
                ->setLastValidationStatus($this->normalizeValidationStatus($addressValidated->lastValidationStatus ?? 'validated'))
                ->setLastValidationScore($addressValidated->lastValidationScore)
                ->setValidationRaw($addressValidated->raw)
                ->setValidationVerdict($validationIssues)
                ->setValidationDeliverable($addressValidated->addressValidationVerdict?->deliverable)
                ->setValidationGranularity($addressValidated->addressValidationVerdict?->granularity)
                ->setValidationQuality($addressValidated->addressValidationVerdict?->quality);

            $snapshot = $this->createEvidenceSnapshot(
                $entity,
                $addressValidated,
                $validatedAt,
                $normalizedSnapshot,
                $providerDigest,
                $validationIssues,
            );
            if ($snapshot instanceof AddressEvidenceSnapshotEntity) {
                $this->entityManager->persist($snapshot);
            }

            $outbox = (new AddressOutboxEntity())
                ->setEventName('AddressValidatedApplied')
                ->setEventVersion(1)
                ->setPayload($this->encodePayload(AddressOutboxEventMessage::decoratePayload('AddressValidatedApplied', $this->payloadFactory->outboxPayload(
                    new AddressValidatedOutboxContext(
                        id: $id,
                        ownerId: $ownerId,
                        vendorId: $vendorId,
                        fingerprint: $fingerprint,
                        validatedAt: $validatedAt,
                        rawSha256: $rawSha256,
                        governanceStatus: $this->normalizeGovernanceStatus($addressValidated->governanceStatus),
                        duplicateOfId: $this->sanitizeGovernanceLink($addressValidated->duplicateOfId, $id),
                        supersededById: $this->sanitizeGovernanceLink($addressValidated->supersededById, $id),
                        aliasOfId: $this->sanitizeGovernanceLink($addressValidated->aliasOfId, $id),
                        conflictWithId: $this->sanitizeGovernanceLink($addressValidated->conflictWithId, $id),
                        revalidationDueAt: $addressValidated->revalidationDueAt?->format(DATE_ATOM),
                        revalidationPolicy: $addressValidated->revalidationPolicy,
                        lastValidationStatus: $this->normalizeValidationStatus($addressValidated->lastValidationStatus ?? 'validated'),
                        lastValidationScore: $addressValidated->lastValidationScore,
                        evidenceSnapshotId: $snapshot instanceof AddressEvidenceSnapshotEntity ? $snapshot->getId() : null,
                        providerDigest: $providerDigest,
                    ),
                    $addressValidated,
                ))))
                ->setCreatedAt($now);
            $this->entityManager->persist($outbox);

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Throwable $throwable) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            if ($throwable instanceof \RuntimeException) {
                throw $throwable;
            }

            throw new \RuntimeException('apply_failed', previous: $throwable);
        }
    }

    private function findAddressEntity(string $id, ?string $ownerId, ?string $vendorId): ?AddressEntity
    {
        $criteria = ['id' => $id, 'deletedAt' => null];
        if (null !== $ownerId) {
            $criteria['ownerId'] = $ownerId;
        }
        if (null !== $vendorId) {
            $criteria['vendorId'] = $vendorId;
        }

        $entity = $this->entityManager->getRepository(AddressEntity::class)->findOneBy($criteria);

        return $entity instanceof AddressEntity ? $entity : null;
    }

    private function normalizeValidationStatus(string $status): string
    {
        return match ($status) {
            'validated', 'rejected', 'uncertain' => $status,
            default => 'validated',
        };
    }

    private function normalizeGovernanceStatus(?string $status): string
    {
        return match ($status) {
            'duplicate', 'superseded', 'alias', 'conflict' => $status,
            default => 'canonical',
        };
    }

    private function sanitizeGovernanceLink(?string $linkId, string $currentId): ?string
    {
        $linkId = is_string($linkId) ? trim($linkId) : '';
        if ('' === $linkId || $linkId === $currentId) {
            return null;
        }

        return $linkId;
    }

    /**
     * @param array<string, mixed>|null $normalizedSnapshot
     * @param array<string, mixed>|null $validationIssues
     */
    private function createEvidenceSnapshot(
        AddressEntity $entity,
        AddressValidated $addressValidated,
        \DateTimeImmutable $validatedAt,
        ?array $normalizedSnapshot,
        ?string $providerDigest,
        ?array $validationIssues,
    ): ?AddressEvidenceSnapshotEntity {
        if (!$this->payloadFactory->hasEvidence($addressValidated)) {
            return null;
        }

        $validatedBy = $addressValidated->validationProvider ?? $addressValidated->lastValidationProvider ?? $addressValidated->sourceSystem;
        $validationScore = $addressValidated->lastValidationScore ?? $addressValidated->addressValidationVerdict?->quality;
        if (null === $validationIssues && null !== $addressValidated->raw && isset($addressValidated->raw['issues']) && is_array($addressValidated->raw['issues'])) {
            $validationIssues = $addressValidated->raw['issues'];
        }

        $snapshot = (new AddressEvidenceSnapshotEntity())
            ->setId(bin2hex(random_bytes(16)))
            ->setAddress($entity)
            ->setOwnerId($entity->getOwnerId())
            ->setVendorId($entity->getVendorId())
            ->setSourceSystem($addressValidated->sourceSystem)
            ->setSourceType($addressValidated->sourceType)
            ->setSourceReference($addressValidated->sourceReference)
            ->setValidatedBy($validatedBy)
            ->setValidatedAt($addressValidated->validatedAt)
            ->setNormalizationVersion($addressValidated->normalizationVersion)
            ->setRawInputSnapshot($addressValidated->rawInput)
            ->setNormalizedSnapshot($normalizedSnapshot)
            ->setValidationStatus($this->normalizeValidationStatus($addressValidated->lastValidationStatus ?? 'validated'))
            ->setValidationScore($validationScore)
            ->setValidationIssues($validationIssues)
            ->setProviderDigest($providerDigest)
            ->setCreatedAt($validatedAt);

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodePayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            throw new \RuntimeException('payload_encode_failed');
        }

        return $json;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validationIssues(AddressValidated $addressValidated): ?array
    {
        if ($addressValidated->addressValidationVerdict instanceof \App\Contract\Message\AddressValidationVerdict) {
            return $addressValidated->addressValidationVerdict->jsonSerialize();
        }

        if (null !== $addressValidated->raw && isset($addressValidated->raw['issues']) && is_array($addressValidated->raw['issues'])) {
            return $addressValidated->raw['issues'];
        }

        return null;
    }
}
