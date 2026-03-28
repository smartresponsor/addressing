<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);


namespace App\Service\Application;

use App\EntityInterface\Record\AddressEvidenceSnapshotInterface;
use App\EntityInterface\Record\AddressInterface;
use App\RepositoryInterface\Persistence\AddressRepositoryInterface;

final class AddressService
{
    public function __construct(private readonly AddressRepositoryInterface $repo)
    {
    }

    public function create(AddressInterface $address): void
    {
        $this->repo->create($address);
    }

    public function update(AddressInterface $address): void
    {
        $this->repo->update($address);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{items: list<AddressInterface>, nextCursor: ?string}
     */
    public function search(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        int $limit,
        ?string $cursor,
        array $filters = [],
    ): array {
        return $this->repo->findPage($ownerId, $vendorId, $countryCode, $q, $limit, $cursor, $filters);
    }

    /** @param array<string, mixed> $patch */
    public function patchOperational(string $id, ?string $ownerId, ?string $vendorId, array $patch): bool
    {
        return $this->repo->patchOperational($id, $ownerId, $vendorId, $patch);
    }

    public function appendEvidenceSnapshot(AddressInterface $address): ?AddressEvidenceSnapshotInterface
    {
        return $this->repo->appendEvidenceSnapshot($address);
    }

    public function getLatestEvidenceSnapshot(string $addressId, ?string $ownerId, ?string $vendorId): ?AddressEvidenceSnapshotInterface
    {
        return $this->repo->getLatestEvidenceSnapshot($addressId, $ownerId, $vendorId);
    }

    /**
     * @return array{items: list<AddressEvidenceSnapshotInterface>, nextCursor: ?string}
     */
    public function evidenceHistory(string $addressId, ?string $ownerId, ?string $vendorId, int $limit, ?string $cursor): array
    {
        return $this->repo->findEvidenceHistoryPage($addressId, $ownerId, $vendorId, $limit, $cursor);
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
    public function evidenceHistorySummary(string $addressId, ?string $ownerId, ?string $vendorId): array
    {
        $cursor = null;
        $items = [];

        do {
            $page = $this->repo->findEvidenceHistoryPage($addressId, $ownerId, $vendorId, 200, $cursor);
            foreach ($page['items'] as $item) {
                $items[] = $item;
            }
            $cursor = $page['nextCursor'];
        } while (null !== $cursor);

        $providers = [];
        $latestValidatedAt = null;
        $latestCreatedAt = null;
        $statusPending = 0;
        $statusValidated = 0;
        $statusRejected = 0;

        foreach ($items as $item) {
            $status = $item->validationStatus();
            if ('pending' === $status) {
                ++$statusPending;
            } elseif ('validated' === $status) {
                ++$statusValidated;
            } elseif ('rejected' === $status) {
                ++$statusRejected;
            }

            $provider = $item->validatedBy();
            if (null !== $provider && '' !== trim($provider)) {
                $providers[$provider] = true;
            }

            $validatedAt = $item->validatedAt();
            if (null !== $validatedAt && (null === $latestValidatedAt || $validatedAt > $latestValidatedAt)) {
                $latestValidatedAt = $validatedAt;
            }

            $createdAt = $item->createdAt();
            if (null === $latestCreatedAt || $createdAt > $latestCreatedAt) {
                $latestCreatedAt = $createdAt;
            }
        }

        return [
            'totalSnapshots' => count($items),
            'statusPending' => $statusPending,
            'statusValidated' => $statusValidated,
            'statusRejected' => $statusRejected,
            'distinctProviders' => count($providers),
            'latestValidatedAt' => $latestValidatedAt,
            'latestCreatedAt' => $latestCreatedAt,
        ];
    }

    public function dedupe(?string $dedupeKey): ?AddressInterface
    {
        if (null === $dedupeKey) {
            return null;
        }

        return $this->repo->findByDedupeKey($dedupeKey);
    }

    public function get(string $id, ?string $ownerId, ?string $vendorId): ?AddressInterface
    {
        return $this->repo->get($id, $ownerId, $vendorId);
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
    public function governanceClusterSummary(string $addressId, ?string $ownerId, ?string $vendorId): array
    {
        return $this->repo->summarizeGovernanceCluster($addressId, $ownerId, $vendorId);
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
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
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
        $summary = $this->repo->summarizeOperationalQueues($ownerId, $vendorId, $countryCode, $q, $filters);

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
        ?string $ownerId,
        ?string $vendorId,
        ?string $q = null,
        array $filters = [],
    ): array {
        return $this->repo->summarizeCountryPortfolio($ownerId, $vendorId, $q, $filters);
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
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        array $filters = [],
    ): array {
        return $this->repo->summarizeSourcePortfolio($ownerId, $vendorId, $countryCode, $q, $filters);
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
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        array $filters = [],
    ): array {
        return $this->repo->summarizeValidationPortfolio($ownerId, $vendorId, $countryCode, $q, $filters);
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
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $q,
        array $filters = [],
    ): array {
        return $this->repo->summarizeNormalizationPortfolio($ownerId, $vendorId, $countryCode, $q, $filters);
    }
}
