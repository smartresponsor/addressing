<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\RepositoryInterface\Persistence;

interface AddressGovernanceRepositoryInterface
{
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
    public function summarizeGovernanceCluster(string $addressId, ?string $ownerId, ?string $vendorId): array;
}
