<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Application;

use App\Addressing\EntityInterface\Record\AddressEvidenceSnapshotInterface;
use App\Addressing\EntityInterface\Record\AddressInterface;
use App\Addressing\RepositoryInterface\AddressEvidenceRepositoryInterface;

final readonly class AddressEvidenceService
{
    public function __construct(private AddressEvidenceRepositoryInterface $addressEvidenceRepository)
    {
    }

    public function appendSnapshot(AddressInterface $address): ?AddressEvidenceSnapshotInterface
    {
        return $this->addressEvidenceRepository->appendEvidenceSnapshot($address);
    }

    public function latestSnapshot(string $addressId, ?string $ownerId, ?string $vendorId): ?AddressEvidenceSnapshotInterface
    {
        return $this->addressEvidenceRepository->getLatestEvidenceSnapshot($addressId, $ownerId, $vendorId);
    }

    /**
     * @return array{'items': list<AddressEvidenceSnapshotInterface>, 'nextCursor': ?string}
     */
    public function history(string $addressId, ?string $ownerId, ?string $vendorId, int $limit, ?string $cursor): array
    {
        return $this->addressEvidenceRepository->findEvidenceHistoryPage($addressId, $ownerId, $vendorId, $limit, $cursor);
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
    public function historySummary(string $addressId, ?string $ownerId, ?string $vendorId): array
    {
        $summary = $this->emptyEvidenceHistorySummary();
        $providers = [];
        $cursor = null;

        do {
            $page = $this->addressEvidenceRepository->findEvidenceHistoryPage($addressId, $ownerId, $vendorId, 200, $cursor);
            foreach ($page['items'] as $item) {
                $this->accumulateEvidenceHistorySummary($summary, $providers, $item);
            }
            $cursor = $page['nextCursor'];
        } while (null !== $cursor);

        $summary['distinctProviders'] = count($providers);

        return [
            'totalSnapshots' => (int) $summary['totalSnapshots'],
            'statusPending' => (int) $summary['statusPending'],
            'statusValidated' => (int) $summary['statusValidated'],
            'statusRejected' => (int) $summary['statusRejected'],
            'distinctProviders' => (int) $summary['distinctProviders'],
            'latestValidatedAt' => is_string($summary['latestValidatedAt']) ? $summary['latestValidatedAt'] : null,
            'latestCreatedAt' => is_string($summary['latestCreatedAt']) ? $summary['latestCreatedAt'] : null,
        ];
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
    private function accumulateEvidenceHistorySummary(array &$summary, array &$providers, AddressEvidenceSnapshotInterface $item): void
    {
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
