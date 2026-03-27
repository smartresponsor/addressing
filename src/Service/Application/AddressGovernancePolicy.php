<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */
declare(strict_types=1);

namespace App\Service\Application;

use App\Contract\Message\AddressRecordPolicy;

final class AddressGovernancePolicy
{
    /** @var array<string, list<string>> */
    private const ALLOWED_TRANSITIONS = [
        'canonical' => ['canonical', 'duplicate', 'superseded', 'alias', 'conflict'],
        'duplicate' => ['duplicate', 'conflict'],
        'superseded' => ['superseded', 'conflict'],
        'alias' => ['alias', 'conflict'],
        'conflict' => ['canonical', 'duplicate', 'superseded', 'alias', 'conflict'],
    ];

    /**
     * @param array<string, mixed> $patch
     *
     * @return array<string, mixed>
     */
    public static function normalizePatch(string $currentStatus, string $currentId, array $patch): array
    {
        if (!array_key_exists('governanceStatus', $patch)) {
            return [];
        }

        $targetStatus = AddressRecordPolicy::normalizeGovernanceStatus(self::asNullableString($patch['governanceStatus'] ?? null));
        $currentStatus = AddressRecordPolicy::normalizeGovernanceStatus($currentStatus);

        if (!in_array($targetStatus, self::ALLOWED_TRANSITIONS[$currentStatus] ?? ['canonical'], true)) {
            throw new \RuntimeException(sprintf('Invalid governance transition from "%s" to "%s".', $currentStatus, $targetStatus));
        }

        $normalized = [
            'governance_status' => $targetStatus,
            'duplicate_of_id' => null,
            'superseded_by_id' => null,
            'alias_of_id' => null,
            'conflict_with_id' => null,
        ];

        $linkMap = [
            'duplicate' => ['duplicate_of_id', 'duplicateOfId'],
            'superseded' => ['superseded_by_id', 'supersededById'],
            'alias' => ['alias_of_id', 'aliasOfId'],
            'conflict' => ['conflict_with_id', 'conflictWithId'],
        ];

        if ('canonical' === $targetStatus) {
            return $normalized;
        }

        [$column, $inputKey] = $linkMap[$targetStatus];
        $linkId = self::sanitizeLink(self::asNullableString($patch[$inputKey] ?? null), $currentId);
        if (null === $linkId) {
            throw new \RuntimeException(sprintf('Governance status "%s" requires a non-self link id.', $targetStatus));
        }

        $normalized[$column] = $linkId;

        return $normalized;
    }

    private static function sanitizeLink(?string $linkId, string $currentId): ?string
    {
        $linkId = self::asNullableString($linkId);
        $currentId = self::asNullableString($currentId);
        if (null === $linkId || null === $currentId || $linkId === $currentId) {
            return null;
        }

        return $linkId;
    }

    private static function asNullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
