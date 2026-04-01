<?php

declare(strict_types=1);

namespace App\Integration\Persistence;

final readonly class AddressTenantScopeSqlHelper
{
    public function whereClause(?string $ownerId, ?string $vendorId): string
    {
        if (null !== $ownerId && null !== $vendorId) {
            return '(owner_id = :owner_id AND vendor_id = :vendor_id)';
        }
        if (null !== $ownerId) {
            return '(owner_id = :owner_id)';
        }
        if (null !== $vendorId) {
            return '(vendor_id = :vendor_id)';
        }

        return '1 = 1';
    }

    /** @return array<string, string> */
    public function params(?string $ownerId, ?string $vendorId): array
    {
        $params = [];
        if (null !== $ownerId) {
            $params[':owner_id'] = $ownerId;
        }
        if (null !== $vendorId) {
            $params[':vendor_id'] = $vendorId;
        }

        return $params;
    }
}
