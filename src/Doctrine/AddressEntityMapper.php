<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\AddressEntity;
use App\Entity\AddressEvidenceSnapshotEntity;
use App\Entity\Record\AddressData;
use App\EntityInterface\Record\AddressEvidenceSnapshotInterface;

final class AddressEntityMapper
{
    public function toDoctrine(AddressData $record): AddressEntity
    {
        return (new AddressEntity())
            ->setId($record->id())
            ->setOwnerId($record->ownerId())
            ->setVendorId($record->vendorId())
            ->setLine1($record->line1())
            ->setLine2($record->line2())
            ->setCity($record->city())
            ->setRegion($record->region())
            ->setPostalCode($record->postalCode())
            ->setCountryCode($record->countryCode())
            ->setLine1Norm($record->line1Norm())
            ->setCityNorm($record->cityNorm())
            ->setRegionNorm($record->regionNorm())
            ->setPostalCodeNorm($record->postalCodeNorm())
            ->setLatitude($record->latitude())
            ->setLongitude($record->longitude())
            ->setGeohash($record->geohash())
            ->setValidationStatus($record->validationStatus())
            ->setValidationProvider($record->validationProvider())
            ->setValidatedAt($this->parseDateTime($record->validatedAt()))
            ->setDedupeKey($record->dedupeKey())
            ->setCreatedAt($this->requiredDateTime($record->createdAt()))
            ->setUpdatedAt($this->parseDateTime($record->updatedAt()))
            ->setDeletedAt($this->parseDateTime($record->deletedAt()))
            ->setValidationFingerprint($record->validationFingerprint())
            ->setValidationRaw($record->validationRaw())
            ->setValidationVerdict($record->validationVerdict())
            ->setValidationDeliverable($record->validationDeliverable())
            ->setValidationGranularity($record->validationGranularity())
            ->setValidationQuality($record->validationQuality())
            ->setSourceSystem($record->sourceSystem())
            ->setSourceType($record->sourceType())
            ->setSourceReference($record->sourceReference())
            ->setNormalizationVersion($record->normalizationVersion())
            ->setRawInputSnapshot($record->rawInputSnapshot())
            ->setNormalizedSnapshot($record->normalizedSnapshot())
            ->setProviderDigest($record->providerDigest())
            ->setGovernanceStatus($record->governanceStatus())
            ->setDuplicateOfId($record->duplicateOfId())
            ->setSupersededById($record->supersededById())
            ->setAliasOfId($record->aliasOfId())
            ->setConflictWithId($record->conflictWithId())
            ->setRevalidationDueAt($this->parseDateTime($record->revalidationDueAt()))
            ->setRevalidationPolicy($record->revalidationPolicy())
            ->setLastValidationProvider($record->lastValidationProvider())
            ->setLastValidationStatus($record->lastValidationStatus())
            ->setLastValidationScore($record->lastValidationScore());
    }

    public function fromDoctrine(AddressEntity $entity): AddressData
    {
        return new AddressData(
            id: $entity->getId(),
            ownerId: $entity->getOwnerId(),
            vendorId: $entity->getVendorId(),
            line1: $entity->getLine1(),
            line2: $entity->getLine2(),
            city: $entity->getCity(),
            region: $entity->getRegion(),
            postalCode: $entity->getPostalCode(),
            countryCode: $entity->getCountryCode(),
            line1Norm: $entity->getLine1Norm(),
            cityNorm: $entity->getCityNorm(),
            regionNorm: $entity->getRegionNorm(),
            postalCodeNorm: $entity->getPostalCodeNorm(),
            latitude: $entity->getLatitude(),
            longitude: $entity->getLongitude(),
            geohash: $entity->getGeohash(),
            validationStatus: $entity->getValidationStatus(),
            validationProvider: $entity->getValidationProvider(),
            validatedAt: $this->formatDateTime($entity->getValidatedAt()),
            dedupeKey: $entity->getDedupeKey(),
            createdAt: $this->formatDateTime($entity->getCreatedAt()) ?? $entity->getCreatedAt()->format(DATE_ATOM),
            updatedAt: $this->formatDateTime($entity->getUpdatedAt()),
            deletedAt: $this->formatDateTime($entity->getDeletedAt()),
            validationFingerprint: $entity->getValidationFingerprint(),
            validationRaw: $entity->getValidationRaw(),
            validationVerdict: $entity->getValidationVerdict(),
            validationDeliverable: $entity->getValidationDeliverable(),
            validationGranularity: $entity->getValidationGranularity(),
            validationQuality: $entity->getValidationQuality(),
            sourceSystem: $entity->getSourceSystem(),
            sourceType: $entity->getSourceType(),
            sourceReference: $entity->getSourceReference(),
            normalizationVersion: $entity->getNormalizationVersion(),
            rawInputSnapshot: $entity->getRawInputSnapshot(),
            normalizedSnapshot: $entity->getNormalizedSnapshot(),
            providerDigest: $entity->getProviderDigest(),
            governanceStatus: $entity->getGovernanceStatus(),
            duplicateOfId: $entity->getDuplicateOfId(),
            supersededById: $entity->getSupersededById(),
            aliasOfId: $entity->getAliasOfId(),
            conflictWithId: $entity->getConflictWithId(),
            revalidationDueAt: $this->formatDateTime($entity->getRevalidationDueAt()),
            revalidationPolicy: $entity->getRevalidationPolicy(),
            lastValidationProvider: $entity->getLastValidationProvider(),
            lastValidationStatus: $entity->getLastValidationStatus(),
            lastValidationScore: $entity->getLastValidationScore(),
        );
    }

    public function toDoctrineSnapshot(AddressEvidenceSnapshotInterface $record, AddressEntity $address): AddressEvidenceSnapshotEntity
    {
        return (new AddressEvidenceSnapshotEntity())
            ->setId($record->id())
            ->setAddress($address)
            ->setOwnerId($record->ownerId())
            ->setVendorId($record->vendorId())
            ->setSourceSystem($record->sourceSystem())
            ->setSourceType($record->sourceType())
            ->setSourceReference($record->sourceReference())
            ->setValidatedBy($record->validatedBy())
            ->setValidatedAt($this->parseDateTime($record->validatedAt()))
            ->setNormalizationVersion($record->normalizationVersion())
            ->setRawInputSnapshot($record->rawInputSnapshot())
            ->setNormalizedSnapshot($record->normalizedSnapshot())
            ->setValidationStatus($record->validationStatus())
            ->setValidationScore($record->validationScore())
            ->setValidationIssues($record->validationIssues())
            ->setProviderDigest($record->providerDigest())
            ->setCreatedAt($this->requiredDateTime($record->createdAt()));
    }

    private function parseDateTime(?string $value): ?\DateTimeImmutable
    {
        return null === $value || '' === $value ? null : new \DateTimeImmutable($value);
    }

    private function requiredDateTime(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value);
    }

    private function formatDateTime(?\DateTimeImmutable $value): ?string
    {
        return $value?->format(DATE_ATOM);
    }
}
