<?php

declare(strict_types=1);

namespace App\Repository\Persistence;

use App\Entity\AddressEntity;
use App\RepositoryInterface\Persistence\AddressGovernanceRepositoryInterface;

final readonly class DoctrineAddressGovernanceRepository extends AbstractDoctrineAddressRepository implements AddressGovernanceRepositoryInterface
{
    #[\Override]
    public function summarizeGovernanceCluster(string $addressId, ?string $ownerId, ?string $vendorId): array
    {
        $currentEntity = $this->findDoctrineAddress($addressId, $ownerId, $vendorId);
        if (!$currentEntity instanceof AddressEntity) {
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

        $current = $this->mapper->fromDoctrine($currentEntity);
        $entities = $this->fetchFilteredAddresses($ownerId, $vendorId, null, null, [], false, false, false);
        $relatedIds = [];
        $primaryLinkId = $this->governanceLinkId($current);
        if (null !== $primaryLinkId) {
            $relatedIds[] = $primaryLinkId;
        }
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

        $relatedIds = array_values(array_unique($relatedIds));
        $inboundLinkedTotal = $duplicateChildren + $supersededChildren + $aliasChildren + $conflictPeers;

        return [
            'addressId' => $addressId,
            'governanceStatus' => $current->governanceStatus(),
            'primaryLinkId' => $primaryLinkId,
            'linkedToAnother' => null !== $primaryLinkId,
            'duplicateChildren' => $duplicateChildren,
            'supersededChildren' => $supersededChildren,
            'aliasChildren' => $aliasChildren,
            'conflictPeers' => $conflictPeers,
            'inboundLinkedTotal' => $inboundLinkedTotal,
            'clusterSize' => 1 + $inboundLinkedTotal + (null !== $primaryLinkId ? 1 : 0),
            'relatedAddressIds' => $relatedIds,
        ];
    }
}
