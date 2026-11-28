<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 */
declare(strict_types=1);

namespace App\Addressing\Message;

/**
 * Carries canonical Addressing outbox event metadata and schema-version information across asynchronous boundaries.
 */
final class AddressOutboxEventMessage
{
    public const string SCHEMA_VERSION = 'address-outbox.v1';

    /** @var array<string, int> */
    private const array EVENT_VERSIONS = [
        'AddressCreated' => 1,
        'AddressUpdated' => 1,
        'AddressDeleted' => 1,
        'AddressOperationalPatched' => 1,
        'AddressValidatedApplied' => 1,
    ];

    /**
     * Resolves the stable schema version assigned to one supported Addressing event name.
     */
    public static function eventVersion(string $eventName): int
    {
        if (!isset(self::EVENT_VERSIONS[$eventName])) {
            throw new \InvalidArgumentException('unknown_address_event_name');
        }

        return self::EVENT_VERSIONS[$eventName];
    }

    /**
     * Enriches an event payload with canonical Addressing schema, version, and occurrence metadata.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public static function decoratePayload(string $eventName, array $payload): array
    {
        $version = self::eventVersion($eventName);
        $occurredAt = new \DateTimeImmutable();

        return [
            'eventName' => $eventName,
            'schemaVersion' => self::SCHEMA_VERSION,
            'eventVersion' => $version,
            'occurredAt' => $occurredAt->format(DATE_ATOM),
        ] + $payload;
    }

    /**
     * Enforces persisted event invariants before attaching version metadata.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public static function decoratePersistedPayload(string $eventName, array $payload): array
    {
        self::eventVersion($eventName);
        self::requireNonBlank($payload['id'] ?? null, 'id');

        foreach (['ownerId', 'vendorId', 'provider', 'granularity'] as $field) {
            if (null !== ($payload[$field] ?? null)) {
                self::requireNonBlank($payload[$field], $field);
            }
        }

        $dateField = match ($eventName) {
            'AddressCreated' => 'createdAt',
            'AddressUpdated', 'AddressOperationalPatched' => 'updatedAt',
            'AddressDeleted' => 'deletedAt',
            'AddressValidatedApplied' => 'validatedAt',
            default => throw new \InvalidArgumentException('unknown_address_event_name'),
        };
        $timestamp = self::requireNonBlank($payload[$dateField] ?? null, $dateField);
        $payload[$dateField] = self::canonicalTimestamp($timestamp, $dateField);

        if ('AddressCreated' === $eventName) {
            $country = strtoupper(trim(self::requireNonBlank($payload['countryCode'] ?? null, 'countryCode')));
            if (1 !== preg_match('/^[A-Z]{2}$/D', $country)) {
                throw new \InvalidArgumentException('outbox_invalid_countryCode');
            }
            $payload['countryCode'] = $country;
        }

        return self::decoratePayload($eventName, $payload);
    }

    private static function canonicalTimestamp(string $value, string $field): string
    {
        foreach ([DATE_ATOM, 'Y-m-d H:i:sP'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if (false !== $date && false === \DateTimeImmutable::getLastErrors()) {
                return $date->format(DATE_ATOM);
            }
        }

        throw new \InvalidArgumentException('outbox_invalid_'.$field);
    }

    private static function requireNonBlank(mixed $value, string $field): string
    {
        if (!is_string($value) || '' === trim($value)) {
            throw new \InvalidArgumentException('outbox_invalid_'.$field);
        }

        return $value;
    }

    /**
     * Exposes the supported Addressing event-version registry for diagnostics and contract inspection.
     *
     * @return array<string, int>
     */
    public static function eventVersions(): array
    {
        return self::EVENT_VERSIONS;
    }
}
