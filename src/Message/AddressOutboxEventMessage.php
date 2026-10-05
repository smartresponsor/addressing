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
     * Exposes the supported Addressing event-version registry for diagnostics and contract inspection.
     *
     * @return array<string, int>
     */
    public static function eventVersions(): array
    {
        return self::EVENT_VERSIONS;
    }
}
