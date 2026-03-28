<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

declare(strict_types=1);

namespace App\Contract\Message;

final class AddressRecordPolicy
{
    /** @var list<string> */
    public const array VALIDATION_STATUSES = ['unknown', 'pending', 'normalized', 'validated', 'rejected', 'uncertain', 'overridden'];

    /** @var list<string> */
    public const array SOURCE_TYPES = ['manual', 'import', 'partner', 'validator', 'override', 'migration'];

    /** @var list<string> */
    public const array GOVERNANCE_STATUSES = ['canonical', 'duplicate', 'superseded', 'alias', 'conflict'];

    /** @var list<string> */
    public const array REVALIDATION_POLICIES = ['manual', 'on-change', 'daily', 'weekly', 'monthly', 'quarterly', 'semiannual', 'annual'];

    /** @var list<string> */
    public const array LAST_VALIDATION_STATUSES = ['normalized', 'validated', 'rejected', 'uncertain', 'overridden'];

    public static function normalizeValidationStatus(?string $status, string $default = 'unknown'): string
    {
        $normalized = self::normalizeToken($status);

        return self::inAllowed($normalized, self::VALIDATION_STATUSES) ? $normalized : $default;
    }

    public static function normalizeSourceType(?string $sourceType): ?string
    {
        $normalized = self::normalizeToken($sourceType);

        return self::inAllowed($normalized, self::SOURCE_TYPES) ? $normalized : null;
    }

    public static function normalizeGovernanceStatus(?string $status, string $default = 'canonical'): string
    {
        $normalized = self::normalizeToken($status);

        return self::inAllowed($normalized, self::GOVERNANCE_STATUSES) ? $normalized : $default;
    }

    public static function normalizeRevalidationPolicy(?string $policy): ?string
    {
        $normalized = self::normalizeToken($policy);

        return self::inAllowed($normalized, self::REVALIDATION_POLICIES) ? $normalized : null;
    }

    public static function normalizeLastValidationStatus(?string $status): ?string
    {
        $normalized = self::normalizeToken($status);

        return self::inAllowed($normalized, self::LAST_VALIDATION_STATUSES) ? $normalized : null;
    }

    private static function normalizeToken(?string $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return strtolower(trim($value));
    }

    /** @param list<string> $allowed */
    private static function inAllowed(string $value, array $allowed): bool
    {
        return '' !== $value && in_array($value, $allowed, true);
    }
}
