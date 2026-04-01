<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\RepositoryInterface\Persistence;

use App\EntityInterface\Record\AddressEvidenceSnapshotInterface;
use App\EntityInterface\Record\AddressInterface;

interface AddressRepositoryInterface
{
    public function create(AddressInterface $address): void;

    public function update(AddressInterface $address): void;

    public function get(string $id, ?string $ownerId, ?string $vendorId): ?AddressInterface;

    public function delete(string $id, ?string $ownerId, ?string $vendorId): void;

    public function findByDedupeKey(string $dedupeKey): ?AddressInterface;

    public function appendEvidenceSnapshot(AddressInterface $address): ?AddressEvidenceSnapshotInterface;

    public function getLatestEvidenceSnapshot(string $addressId, ?string $ownerId, ?string $vendorId): ?AddressEvidenceSnapshotInterface;

    /**
     * @return array{items: list<AddressEvidenceSnapshotInterface>, nextCursor: ?string}
     */
    public function findEvidenceHistoryPage(string $addressId, ?string $ownerId, ?string $vendorId, int $limit, ?string $cursor): array;

    /**
     * @param array<string, mixed> $patch
     */
    public function patchOperational(string $id, ?string $ownerId, ?string $vendorId, array $patch): bool;

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{items: list<AddressInterface>, nextCursor: ?string}
     */
    public function findPage(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        int $limit,
        ?string $cursor,
        array $filters = [],
    ): array;

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *   total:int,
     *   dueForRevalidation:int,
     *   evidenceMissing:int,
     *   uncertainValidation:int,
     *   conflictReview:int,
     *   duplicateReview:int,
     *   staleNormalizationVersion:int
     * }
     */

    /**
     * @return array{
     *   addressId:string,
     *   governanceStatus:?string,
     *   primaryLinkId:?string,
     *   linkedToAnother:bool,
     *   duplicateChildren:int,
     *   supersededChildren:int,
     *   aliasChildren:int,
     *   conflictPeers:int,
     *   inboundLinkedTotal:int,
     *   clusterSize:int,
     *   relatedAddressIds:list<string>
     * }
     */
    public function summarizeGovernanceCluster(string $addressId, ?string $ownerId, ?string $vendorId): array;

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *   total:int,
     *   dueForRevalidation:int,
     *   evidenceMissing:int,
     *   uncertainValidation:int,
     *   conflictReview:int,
     *   duplicateReview:int,
     *   staleNormalizationVersion:int
     * }
     */
    public function summarizeOperationalQueues(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        array $filters = [],
    ): array;

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   countryCode:string,
     *   total:int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   evidenceMissing:int,
     *   dueForRevalidation:int,
     *   uncertainValidation:int
     * }>
     */
    public function summarizeCountryPortfolio(
        ?string $ownerId,
        ?string $vendorId,
        ?string $q,
        array $filters = [],
    ): array;

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   sourceSystem:string,
     *   sourceType:string,
     *   total:int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   evidenceMissing:int,
     *   dueForRevalidation:int,
     *   uncertainValidation:int
     * }>
     */
    public function summarizeSourcePortfolio(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        array $filters = [],
    ): array;

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   validationProvider:string,
     *   validationStatus:string,
     *   total:int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   evidenceMissing:int,
     *   dueForRevalidation:int,
     *   uncertainValidation:int
     * }>
     */
    public function summarizeValidationPortfolio(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        array $filters = [],
    ): array;

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   normalizationVersion:string,
     *   validationStatus:string,
     *   total:int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   evidenceMissing:int,
     *   dueForRevalidation:int,
     *   uncertainValidation:int,
     *   staleNormalization:int
     * }>
     */
    public function summarizeNormalizationPortfolio(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        array $filters = [],
    ): array;
}
