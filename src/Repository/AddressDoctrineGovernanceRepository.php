<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\RepositoryInterface\AddressGovernanceRepositoryInterface;

/**
 * Builds tenant-scoped governance cluster summaries from persisted Addressing relationships.
 */
final readonly class AddressDoctrineGovernanceRepository extends AddressAbstractDoctrineRepository implements AddressGovernanceRepositoryInterface
{
    /** Summarize outbound and inbound governance relationships for one address within the requested tenant scope. */
    #[\Override]
    public function summarizeGovernanceCluster(string $addressId, ?string $ownerId, ?string $vendorId): array
    {
        $currentEntity = $this->findDoctrineAddress($addressId, $ownerId, $vendorId);
        if (!$currentEntity instanceof AddressEntity) {
            return $this->emptyGovernanceCluster($addressId);
        }

        $addressData = $this->mapper->fromDoctrine($currentEntity);
        $entities = $this->fetchFilteredAddresses($ownerId, $vendorId, null, null, [], false, false, false);
        $relatedIds = [];
        $primaryLinkId = $this->governanceLinkId($addressData);
        if (null !== $primaryLinkId) {
            $relatedIds[] = $primaryLinkId;
        }

        $summary = $this->summarizeInboundGovernanceLinks($entities, $addressId, $relatedIds);
        $inboundLinkedTotal = $summary['duplicateChildren'] + $summary['supersededChildren'] + $summary['aliasChildren'] + $summary['conflictPeers'];

        return [
            'addressId' => $addressId,
            'governanceStatus' => $addressData->governanceStatus(),
            'primaryLinkId' => $primaryLinkId,
            'linkedToAnother' => null !== $primaryLinkId,
            'duplicateChildren' => $summary['duplicateChildren'],
            'supersededChildren' => $summary['supersededChildren'],
            'aliasChildren' => $summary['aliasChildren'],
            'conflictPeers' => $summary['conflictPeers'],
            'inboundLinkedTotal' => $inboundLinkedTotal,
            'clusterSize' => 1 + $inboundLinkedTotal + (null !== $primaryLinkId ? 1 : 0),
            'relatedAddressIds' => $summary['relatedAddressIds'],
        ];
    }

    /**
     * @param list<AddressEntity> $entities
     * @param list<string>        $relatedIds
     *
     * @return array{duplicateChildren: int, supersededChildren: int, aliasChildren: int, conflictPeers: int, relatedAddressIds: list<string>}
     */
    private function summarizeInboundGovernanceLinks(array $entities, string $addressId, array $relatedIds): array
    {
        $duplicateChildren = 0;
        $supersededChildren = 0;
        $aliasChildren = 0;
        $conflictPeers = 0;

        foreach ($entities as $entity) {
            if ($addressId === $entity->getDuplicateOfId()) {
                ++$duplicateChildren;
            }
            if ($addressId === $entity->getSupersededById()) {
                ++$supersededChildren;
            }
            if ($addressId === $entity->getAliasOfId()) {
                ++$aliasChildren;
            }
            if ($addressId === $entity->getConflictWithId()) {
                ++$conflictPeers;
            }

            if (in_array($addressId, [$entity->getDuplicateOfId(), $entity->getSupersededById(), $entity->getAliasOfId(), $entity->getConflictWithId()], true)) {
                $relatedIds[] = $entity->getId();
            }
        }

        return [
            'duplicateChildren' => $duplicateChildren,
            'supersededChildren' => $supersededChildren,
            'aliasChildren' => $aliasChildren,
            'conflictPeers' => $conflictPeers,
            'relatedAddressIds' => array_values(array_unique($relatedIds)),
        ];
    }

    /**
     * @return array{addressId: string, governanceStatus: null, primaryLinkId: null, linkedToAnother: false, duplicateChildren: 0, supersededChildren: 0, aliasChildren: 0, conflictPeers: 0, inboundLinkedTotal: 0, clusterSize: 0, relatedAddressIds: list<string>}
     */
    private function emptyGovernanceCluster(string $addressId): array
    {
        return [
            'addressId' => $addressId,
            'governanceStatus' => null,
            'primaryLinkId' => null,
            'linkedToAnother' => false,
            'duplicateChildren' => 0,
            'supersededChildren' => 0,
            'aliasChildren' => 0,
            'conflictPeers' => 0,
            'inboundLinkedTotal' => 0,
            'clusterSize' => 0,
            'relatedAddressIds' => [],
        ];
    }
}
