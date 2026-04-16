<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Application;

use App\EntityInterface\Record\AddressEvidenceSnapshotInterface;
use App\EntityInterface\Record\AddressInterface;
use App\RepositoryInterface\Persistence\AddressPageCriteria;
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
     * @noinspection PhpTooManyParametersInspection
     *
     * @param array<string, mixed> $filters
     *
     * @return array{'items': list<AddressInterface>, 'nextCursor': ?string}
     */
    public function search(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $query,
        int $limit,
        ?string $cursor,
        array $filters = [],
    ): array {
        $criteria = AddressPageCriteria::forScope($ownerId, $vendorId, $countryCode, $query)
            ->withPagination($limit, $cursor)
            ->withFilters($filters);

        return $this->addressRepository->findPage($criteria);
    }

    /** @param array<string, mixed> $patch */
    public function patchOperational(string $id, ?string $ownerId, ?string $vendorId, array $patch): bool
    {
        return $this->addressRepository->patchOperational($id, $ownerId, $vendorId, $patch);
    }

    public function appendEvidenceSnapshot(AddressInterface $address): ?AddressEvidenceSnapshotInterface
    {
        return $this->addressRepository->appendEvidenceSnapshot($address);
    }

    public function getLatestEvidenceSnapshot(string $addressId, ?string $ownerId, ?string $vendorId): ?AddressEvidenceSnapshotInterface
    {
        return $this->addressRepository->getLatestEvidenceSnapshot($addressId, $ownerId, $vendorId);
    }

    /**
     * @return array{'items': list<AddressEvidenceSnapshotInterface>, 'nextCursor': ?string}
     */
    public function evidenceHistory(string $addressId, ?string $ownerId, ?string $vendorId, int $limit, ?string $cursor): array
    {
        return $this->addressRepository->findEvidenceHistoryPage($addressId, $ownerId, $vendorId, $limit, $cursor);
    }

    /**
     * @return array{
     *   'totalSnapshots':int,
     *   'statusPending':int,
     *   'statusValidated':int,
     *   'statusRejected':int,
     *   'distinctProviders':int,
     *   'latestValidatedAt':?string,
     *   'latestCreatedAt':?string
     * }
     */
    public function evidenceHistorySummary(string $addressId, ?string $ownerId, ?string $vendorId): array
    {
        $summary = $this->emptyEvidenceHistorySummary();
        $providers = [];
        $cursor = null;

        do {
            $page = $this->addressRepository->findEvidenceHistoryPage($addressId, $ownerId, $vendorId, 200, $cursor);
            foreach ($page['items'] as $item) {
                $this->accumulateEvidenceHistorySummary($summary, $providers, $item);
            }
            $cursor = $page['nextCursor'];
        } while (null !== $cursor);

        $summary['distinctProviders'] = count($providers);

        return $summary;
    }

    public function dedupe(?string $dedupeKey): ?AddressInterface
    {
        if (null === $dedupeKey) {
            return null;
        }

        return $this->addressRepository->findByDedupeKey($dedupeKey);
    }

    public function get(string $id, ?string $ownerId, ?string $vendorId): ?AddressInterface
    {
        return $this->addressRepository->get($id, $ownerId, $vendorId);
    }

    public function markDeleted(string $id, ?string $ownerId, ?string $vendorId): void
    {
        $this->addressRepository->delete($id, $ownerId, $vendorId);
    }

    /**
     * @return array{
     *   'addressId':string,
     *   'governanceStatus':?string,
     *   'primaryLinkId':?string,
     *   'linkedToAnother':bool,
     *   'duplicateChildren':int,
     *   'supersededChildren':int,
     *   'aliasChildren':int,
     *   'conflictPeers':int,
     *   'inboundLinkedTotal':int,
     *   'clusterSize':int,
     *   'relatedAddressIds':list<string>
     * }
     */
    public function governanceClusterSummary(string $addressId, ?string $ownerId, ?string $vendorId): array
    {
        return $this->addressRepository->summarizeGovernanceCluster($addressId, $ownerId, $vendorId);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{
     *   'total':int,
     *   'dueForRevalidation':int,
     *   'evidenceMissing':int,
     *   'uncertainValidation':int,
     *   'conflictReview':int,
     *   'duplicateReview':int,
     *   'staleNormalizationVersion':int
     * }
     */
    public function operationalQueueSummary(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $query,
        array $filters = [],
    ): array {
        /** @var array{
         *   'total':int,
         *   'dueForRevalidation':int,
         *   'evidenceMissing':int,
         *   'uncertainValidation':int,
         *   'conflictReview':int,
         *   'duplicateReview':int,
         *   'staleNormalizationVersion':int
         * } $summary
         */
        $summary = $this->addressRepository->summarizeOperationalQueues($ownerId, $vendorId, $countryCode, $query, $filters);

        return $summary;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   countryCode:string,
     *   'total':int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   'evidenceMissing':int,
     *   'dueForRevalidation':int,
     *   'uncertainValidation':int
     * }>
     */
    public function countryPortfolioSummary(
        ?string $ownerId,
        ?string $vendorId,
        ?string $q = null,
        array $filters = [],
    ): array {
        return $this->addressRepository->summarizeCountryPortfolio($ownerId, $vendorId, $q, $filters);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   sourceSystem:string,
     *   sourceType:string,
     *   'total':int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   'evidenceMissing':int,
     *   'dueForRevalidation':int,
     *   'uncertainValidation':int
     * }>
     */
    public function sourcePortfolioSummary(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $query,
        array $filters = [],
    ): array {
        return $this->addressRepository->summarizeSourcePortfolio($ownerId, $vendorId, $countryCode, $query, $filters);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   validationProvider:string,
     *   validationStatus:string,
     *   'total':int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   'evidenceMissing':int,
     *   'dueForRevalidation':int,
     *   'uncertainValidation':int
     * }>
     */
    public function validationPortfolioSummary(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $query,
        array $filters = [],
    ): array {
        return $this->addressRepository->summarizeValidationPortfolio($ownerId, $vendorId, $countryCode, $query, $filters);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array{
     *   normalizationVersion:string,
     *   validationStatus:string,
     *   'total':int,
     *   canonical:int,
     *   duplicate:int,
     *   superseded:int,
     *   alias:int,
     *   conflict:int,
     *   evidenceBacked:int,
     *   'evidenceMissing':int,
     *   'dueForRevalidation':int,
     *   'uncertainValidation':int,
     *   staleNormalization:int
     * }>
     */
    public function normalizationPortfolioSummary(
        ?string $ownerId,
        ?string $vendorId,
        ?string $countryCode,
        ?string $query,
        array $filters = [],
    ): array {
        return $this->addressRepository->summarizeNormalizationPortfolio($ownerId, $vendorId, $countryCode, $query, $filters);
    }

    /**
     * @return array{
     *   'totalSnapshots':int,
     *   'statusPending':int,
     *   'statusValidated':int,
     *   'statusRejected':int,
     *   'distinctProviders':int,
     *   'latestValidatedAt':?string,
     *   'latestCreatedAt':?string
     * }
     */
    private function emptyEvidenceHistorySummary(): array
    {
        return [
            'totalSnapshots' => 0,
            'statusPending' => 0,
            'statusValidated' => 0,
            'statusRejected' => 0,
            'distinctProviders' => 0,
            'latestValidatedAt' => null,
            'latestCreatedAt' => null,
        ];
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<string, true>  $providers
     */
    private function accumulateEvidenceHistorySummary(
        array &$summary,
        array &$providers,
        AddressEvidenceSnapshotInterface $item,
    ): void {
        ++$summary['totalSnapshots'];
        $this->incrementEvidenceValidationStatus($summary, $item->validationStatus());

        $provider = $item->validatedBy();
        if (null !== $provider && '' !== trim($provider)) {
            $providers[$provider] = true;
        }

        $this->keepLatestTimestamp($summary, 'latestValidatedAt', $item->validatedAt());
        $this->keepLatestTimestamp($summary, 'latestCreatedAt', $item->createdAt());
    }

    /** @param array<string, mixed> $summary */
    private function incrementEvidenceValidationStatus(array &$summary, string $status): void
    {
        if ('pending' === $status) {
            ++$summary['statusPending'];

            return;
        }
        if ('validated' === $status) {
            ++$summary['statusValidated'];

            return;
        }
        if ('rejected' === $status) {
            ++$summary['statusRejected'];
        }
    }

    /** @param array<string, mixed> $summary */
    private function keepLatestTimestamp(array &$summary, string $key, ?string $candidate): void
    {
        if (null === $candidate) {
            return;
        }

        $current = $summary[$key];
        if (null === $current || $candidate > $current) {
            $summary[$key] = $candidate;
        }
    }
}
