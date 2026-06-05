<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Application;

use App\RepositoryInterface\Persistence\AddressGovernanceRepositoryInterface;

final readonly class AddressGovernanceSummaryService
{
    public function __construct(private AddressGovernanceRepositoryInterface $governanceRepository)
    {
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
    public function summarize(string $addressId, ?string $ownerId, ?string $vendorId): array
    {
        return $this->governanceRepository->summarizeGovernanceCluster($addressId, $ownerId, $vendorId);
    }
}
