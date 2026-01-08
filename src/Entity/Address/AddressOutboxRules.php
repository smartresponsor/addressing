<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

declare(strict_types=1);

namespace App\Entity\Address;

use App\EntityInterface\Address\AddressInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final class AddressOutboxRules
{
    /** @return array{id: string, ownerId: ?string, vendorId: ?string, countryCode: string, createdAt: string} */
    public static function addressCreatedPayload(AddressInterface $address): array
    {
        return [
            'id' => self::requireId($address->id()),
            'ownerId' => self::optionalNonEmptyString($address->ownerId(), 'ownerId'),
            'vendorId' => self::optionalNonEmptyString($address->vendorId(), 'vendorId'),
            'countryCode' => self::requireCountryCode($address->countryCode()),
            'createdAt' => self::requireDateAtomString($address->createdAt(), 'createdAt'),
        ];
    }

    /** @return array{id: string, updatedAt: string} */
    public static function addressUpdatedPayload(string $id, string $updatedAt): array
    {
        return [
            'id' => self::requireId($id),
            'updatedAt' => self::requireDateAtomString($updatedAt, 'updatedAt'),
        ];
    }

    /** @return array{id: string, deletedAt: string} */
    public static function addressDeletedPayload(string $id, DateTimeImmutable $deletedAt): array
    {
        return [
            'id' => self::requireId($id),
            'deletedAt' => $deletedAt->format(DATE_ATOM),
        ];
    }

    /**
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
        DateTimeImmutable $validatedAt,
        ?bool $deliverable,
        ?string $granularity,
        ?int $quality,
        ?string $rawSha256
    ): array {
        return [
            'id' => self::requireId($id),
            'fingerprint' => self::requireSha256($fingerprint, 'fingerprint'),
            'provider' => self::optionalNonEmptyString($provider, 'provider'),
            'validatedAt' => $validatedAt->format(DATE_ATOM),
            'deliverable' => $deliverable,
            'granularity' => self::optionalNonEmptyString($granularity, 'granularity'),
            'quality' => self::optionalNonNegativeInt($quality, 'quality'),
            'rawSha256' => self::optionalSha256($rawSha256, 'rawSha256'),
        ];
    }

    private static function requireId(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException('outbox id is required');
        }
        return $id;
    }

    private static function requireCountryCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if (strlen($code) !== 2) {
            throw new InvalidArgumentException('outbox countryCode must be ISO-3166 alpha-2');
        }
        return $code;
    }

    private static function requireDateAtomString(string $value, string $field): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException('outbox ' . $field . ' is required');
        }
        $dt = new DateTimeImmutable($value);
        return $dt->format(DATE_ATOM);
    }

    private static function requireSha256(string $value, string $field): string
    {
        $value = strtolower(trim($value));
        if ($value === '' || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new InvalidArgumentException('outbox ' . $field . ' must be sha256 hex');
        }
        return $value;
    }

    private static function optionalSha256(?string $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        return self::requireSha256($value, $field);
    }

    private static function optionalNonEmptyString(?string $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException('outbox ' . $field . ' must not be empty');
        }
        return $value;
    }

    private static function optionalNonNegativeInt(?int $value, string $field): ?int
    {
        if ($value === null) {
            return null;
        }
        if ($value < 0) {
            throw new InvalidArgumentException('outbox ' . $field . ' must be non-negative');
        }
        return $value;
    }
}
