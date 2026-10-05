<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

/**
 * Provides governance-cluster summaries for scoped address records and their canonical relationships.
 */
interface AddressGovernanceRepositoryInterface
{
    /**
     * Summarizes governance relationships, linkage state, and cluster membership for one address.
     *
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
