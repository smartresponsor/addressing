<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Application;

use App\EntityInterface\Record\AddressEvidenceSnapshotInterface;
use App\EntityInterface\Record\AddressInterface;
use App\RepositoryInterface\Persistence\AddressRepositoryInterface;

final readonly class AddressService
{
    public function __construct(private AddressRepositoryInterface $addressRepository)
    {
    }

    public function create(AddressInterface $address): void
    {
        $this->addressRepository->create($address);
    }

    public function update(AddressInterface $address): void
    {
        $this->addressRepository->update($address);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{items: list<AddressInterface>, nextCursor: ?string}
     */
    public function search(
        ?string $owner_id,
        ?string $vendor_id,
        ?string $country_code,
        ?string $query,
        int $limit,
        ?string $cursor,
        array $filters = [],
    ): array {
        return $this->addressRepository->findPage($owner_id, $vendor_id, $country_code, $query, $limit, $cursor, $filters);
    }

    /** @param array<string, mixed> $patch */
    public function patchOperational(string $id, ?string $owner_id, ?string $vendor_id, array $patch): bool
    {
        return $this->addressRepository->patchOperational($id, $owner_id, $vendor_id, $patch);
    }

    public function appendEvidenceSnapshot(AddressInterface $address): ?AddressEvidenceSnapshotInterface
    {
        return $this->addressRepository->appendEvidenceSnapshot($address);
    }

    public function getLatestEvidenceSnapshot(string $addressId, ?string $owner_id, ?string $vendorId): ?AddressEvidenceSnapshotInterface
    {
        return $this->addressRepository->getLatestEvidenceSnapshot($address_id, $owner_id, $vendor_id);
    }

    /**
     * @return array{items: list<AddressEvidenceSnapshotInterface>, nextCursor: ?string}
     */
    public function evidenceHistory(string $addressId, ?string $owner_id, ?string $vendor_id, int $limit, ?string $cursor): array
    {
        return $this->addressRepository->findEvidenceHistoryPage($address_id, $owner_id, $vendor_id, $limit, $cursor);
    }

    /**
     * @return array{
     *   totalSnapshots:int,
     *   statusPending:int,
     *   statusValidated:int,
     *   statusRejected:int,
     *   distinctProviders:int,
     *   latestValidatedAt:?string,
     *   latestCreatedAt:?string
     * }
     */
    public function evidenceHistorySummary(string $addressId, ?string $owner_id, ?string $vendorId): array
    {
        $cursor = null;
        $items = [];

        do {
            $page = $this->addressRepository->findEvidenceHistoryPage($address_id, $owner_id, $vendor_id, 200, $cursor);
            foreach ($page['items'] as $item) {
                $items[] = $item;
            }
            $cursor = $page['nextCursor'];
        } while (null !== $cursor);

        $providers = [];
        $latest_validated_at = null;
        $latest_created_at = null;
        $status_pending = 0;
        $status_validated = 0;
        $status_rejected = 0;

        foreach ($items as $item) {
            $status = $item->validationStatus();
            if ('pending' === $status) {
                ++$status_pending;
            } elseif ('validated' === $status) {
                ++$status_validated;
            } elseif ('rejected' === $status) {
                ++$status_rejected;
            }

            $provider = $item->validatedBy();
            if (null !== $provider && '' !== trim($provider)) {
                $providers[$provider] = true;
            }

            $validatedAt = $item->validatedAt();
            if (null !== $validatedAt && (null === $latest_validated_at || $validatedAt > $latest_validated_at)) {
                $latest_validated_at = $validatedAt;
            }

            $createdAt = $item->createdAt();
            if (null === $latest_created_at || $createdAt > $latest_created_at) {
                $latest_created_at = $createdAt;
            }
        }

        return [
            'totalSnapshots' => count($items),
            'statusPending' => $status_pending,
            'statusValidated' => $status_validated,
            'statusRejected' => $status_rejected,
            'distinctProviders' => count($providers),
            'latestValidatedAt' => $latest_validated_at,
            'latestCreatedAt' => $latest_created_at,
        ];
    }

    public function dedupe(?string $dedupe_key): ?AddressInterface
    {
        if (null === $dedupe_key) {
            return null;
        }

        return $this->addressRepository->findByDedupeKey($dedupe_key);
    }

    public function get(string $id, ?string $owner_id, ?string $vendorId): ?AddressInterface
    {
        return $this->addressRepository->get($id, $owner_id, $vendor_id);
    }

    public function markDeleted(string $id, ?string $owner_id, ?string $vendorId): void
    {
        $this->addressRepository->delete($id, $owner_id, $vendor_id);
    }

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
    public function governanceClusterSummary(string $addressId, ?string $owner_id, ?string $vendorId): array
    {
        return $this->addressRepository->summarizeGovernanceCluster($address_id, $owner_id, $vendor_id);
    }

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
    public function operationalQueueSummary(
        ?string $owner_id,
        ?string $vendor_id,
        ?string $country_code,
        ?string $query,
        array $filters = [],
    ): array {
        /** @var array{
         *   total:int,
         *   dueForRevalidation:int,
         *   evidenceMissing:int,
         *   uncertainValidation:int,
         *   conflictReview:int,
         *   duplicateReview:int,
         *   staleNormalizationVersion:int
         * } $summary
         */
        $summary = $this->addressRepository->summarizeOperationalQueues($owner_id, $vendor_id, $country_code, $query, $filters);

        return $summary;
    }

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
    public function countryPortfolioSummary(
        ?string $owner_id,
        ?string $vendor_id,
        ?string $q = null,
        array $filters = [],
    ): array {
        return $this->addressRepository->summarizeCountryPortfolio($owner_id, $vendor_id, $query, $filters);
    }

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
    public function sourcePortfolioSummary(
        ?string $owner_id,
        ?string $vendor_id,
        ?string $country_code,
        ?string $query,
        array $filters = [],
    ): array {
        return $this->addressRepository->summarizeSourcePortfolio($ownerId, $vendorId, $countryCode, $q, $filters);
    }

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
    public function validationPortfolioSummary(
        ?string $owner_id,
        ?string $vendor_id,
        ?string $country_code,
        ?string $query,
        array $filters = [],
    ): array {
        return $this->addressRepository->summarizeValidationPortfolio($ownerId, $vendorId, $countryCode, $q, $filters);
    }

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
    public function normalizationPortfolioSummary(
        ?string $owner_id,
        ?string $vendor_id,
        ?string $country_code,
        ?string $query,
        array $filters = [],
    ): array {
        return $this->addressRepository->summarizeNormalizationPortfolio($ownerId, $vendorId, $countryCode, $q, $filters);
    }
}
