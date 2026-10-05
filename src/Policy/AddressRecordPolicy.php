<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

declare(strict_types=1);

namespace App\Addressing\Policy;

/**
 * Normalizes persisted and inbound address governance tokens to the canonical Addressing vocabularies.
 */
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

    /**
     * Returns a canonical validation status while preserving the caller-selected fallback for unknown input.
     */
    public static function normalizeValidationStatus(?string $status, string $default = 'unknown'): string
    {
        $normalized = self::normalizeToken($status);

        return self::inAllowed($normalized, self::VALIDATION_STATUSES) ? $normalized : $default;
    }

    /**
     * Returns a canonical source type or null when the supplied provenance token is unsupported.
     */
    public static function normalizeSourceType(?string $sourceType): ?string
    {
        $normalized = self::normalizeToken($sourceType);

        return self::inAllowed($normalized, self::SOURCE_TYPES) ? $normalized : null;
    }

    /**
     * Returns a canonical governance status while preserving the caller-selected fallback for unknown input.
     */
    public static function normalizeGovernanceStatus(?string $status, string $default = 'canonical'): string
    {
        $normalized = self::normalizeToken($status);

        return self::inAllowed($normalized, self::GOVERNANCE_STATUSES) ? $normalized : $default;
    }

    /**
     * Returns a canonical revalidation policy or null when the scheduling token is unsupported.
     */
    public static function normalizeRevalidationPolicy(?string $policy): ?string
    {
        $normalized = self::normalizeToken($policy);

        return self::inAllowed($normalized, self::REVALIDATION_POLICIES) ? $normalized : null;
    }

    /**
     * Returns a canonical terminal validation status or null when no supported status can be derived.
     */
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
