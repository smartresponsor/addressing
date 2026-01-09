<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

declare(strict_types=1);

namespace App\Entity\Address;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 *
 */

/**
 *
 */
final class AddressOutboxRules
{
    /**
     * @param string $id
     * @param string|null $ownerId
     * @param string|null $vendorId
     * @param string $countryCode
     * @param string $createdAt
     * @return array{id: string, ownerId: ?string, vendorId: ?string, countryCode: string, createdAt: string}
     */
    public static function addressCreatedPayload(
        string $id,
        ?string $ownerId,
        ?string $vendorId,
        string $countryCode,
        string $createdAt
    ): array {
        self::assertUlid($id, 'id');
        self::assertOptionalUlid($ownerId, 'ownerId');
        self::assertOptionalUlid($vendorId, 'vendorId');
        $countryCode = self::normalizeCountryCode($countryCode);
        self::assertDateTime($createdAt, 'createdAt');

        return [
            'id' => $id,
            'ownerId' => $ownerId,
            'vendorId' => $vendorId,
            'countryCode' => $countryCode,
            'createdAt' => $createdAt,
        ];
    }

    /**
     * @param string $id
     * @param string $updatedAt
     * @return array{id: string, updatedAt: string}
     */
    public static function addressUpdatedPayload(string $id, string $updatedAt): array
    {
        self::assertUlid($id, 'id');
        self::assertDateTime($updatedAt, 'updatedAt');

        return [
            'id' => $id,
            'updatedAt' => $updatedAt,
        ];
    }

    /**
     * @param string $id
     * @param string $deletedAt
     * @return array{id: string, deletedAt: string}
     */
    public static function addressDeletedPayload(string $id, string $deletedAt): array
    {
        self::assertUlid($id, 'id');
        self::assertDateTime($deletedAt, 'deletedAt');

        return [
            'id' => $id,
            'deletedAt' => $deletedAt,
        ];
    }

    /**
     * @param string $id
     * @param string $fingerprint
     * @param string|null $provider
     * @param string $validatedAt
     * @param bool|null $deliverable
     * @param string|null $granularity
     * @param int|null $quality
     * @param string|null $rawSha256
     * @return array{
     *     id: string,
     *     fingerprint: string,
     *     provider: ?string,
     *     validatedAt: string,
     *     deliverable: ?bool,
     *     granularity: ?string,
     *     quality: ?int,
     *     rawSha256: ?string
     * }
     */
    public static function addressValidatedAppliedPayload(
        string $id,
        string $fingerprint,
        ?string $provider,
        string $validatedAt,
        ?bool $deliverable,
        ?string $granularity,
        ?int $quality,
        ?string $rawSha256
    ): array {
        self::assertUlid($id, 'id');
        self::assertSha256($fingerprint, 'fingerprint');
        self::assertDateTime($validatedAt, 'validatedAt');
        self::assertOptionalSha256($rawSha256, 'rawSha256');

        return [
            'id' => $id,
            'fingerprint' => $fingerprint,
            'provider' => $provider,
            'validatedAt' => $validatedAt,
            'deliverable' => $deliverable,
            'granularity' => $granularity,
            'quality' => $quality,
            'rawSha256' => $rawSha256,
        ];
    }

    /**
     * @param string $id
     * @param string $field
     * @return void
     */
    private static function assertUlid(string $id, string $field): void
    {
        if (!preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id)) {
            throw new InvalidArgumentException($field . '_invalid');
        }
    }

    /**
     * @param string|null $id
     * @param string $field
     * @return void
     */
    private static function assertOptionalUlid(?string $id, string $field): void
    {
        if ($id === null) {
            return;
        }
        self::assertUlid($id, $field);
    }

    /**
     * @param string $countryCode
     * @return string
     */
    private static function normalizeCountryCode(string $countryCode): string
    {
        $countryCode = strtoupper(trim($countryCode));
        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw new InvalidArgumentException('countryCode_invalid');
        }
        return $countryCode;
    }

    /**
     * @param string $value
     * @param string $field
     * @return void
     */
    private static function assertDateTime(string $value, string $field): void
    {
        if (!self::isValidDateTime($value)) {
            throw new InvalidArgumentException($field . '_invalid');
        }
    }

    /**
     * @param string $value
     * @return bool
     */
    private static function isValidDateTime(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $formats = [DATE_ATOM, 'Y-m-d H:i:sP'];
        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value);
            if ($parsed instanceof DateTimeImmutable && $parsed->format($format) === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $value
     * @param string $field
     * @return void
     */
    private static function assertSha256(string $value, string $field): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new InvalidArgumentException($field . '_invalid');
        }
    }

    /**
     * @param string|null $value
     * @param string $field
     * @return void
     */
    private static function assertOptionalSha256(?string $value, string $field): void
    {
        if ($value === null) {
            return;
        }
        self::assertSha256($value, $field);
    }
}
