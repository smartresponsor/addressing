<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Application;

use App\Addressing\RepositoryInterface\AddressGovernanceRepositoryInterface;

final readonly class AddressGovernanceSummaryService
{
    public function __construct(private AddressGovernanceRepositoryInterface $addressGovernanceRepository)
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
        return $this->addressGovernanceRepository->summarizeGovernanceCluster($addressId, $ownerId, $vendorId);
    }
}
