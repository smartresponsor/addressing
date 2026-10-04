<?php

declare(strict_types=1);

namespace App\Addressing\Builder\Persistence;

/**
 * Builds SQL fragments for optional owner/vendor address scoping.
 *
 * An absent owner and vendor intentionally produces an unrestricted predicate;
 * callers that require isolation must resolve at least one scope before calling.
 */
final readonly class AddressTenantScopeSqlBuilder
{
    /** Returns the SQL predicate matching the supplied owner/vendor scope. */
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

    /**
     * Returns the bound parameters matching whereClause() for the same scope.
     *
     * @return array<string, string>
     */
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
