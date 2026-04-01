<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 */
declare(strict_types=1);

namespace App\Contract\Message;

final class AddressOutboxEventContract
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

    public static function eventVersion(string $eventName): int
    {
        if (!isset(self::EVENT_VERSIONS[$eventName])) {
            throw new \InvalidArgumentException('unknown_address_event_name');
        }

        return self::EVENT_VERSIONS[$eventName];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public static function decoratePayload(string $eventName, array $payload): array
    {
        $version = self::eventVersion($eventName);

        return [
            'eventName' => $eventName,
            'schemaVersion' => self::SCHEMA_VERSION,
            'eventVersion' => $version,
            'occurredAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ] + $payload;
    }

    /** @return array<string, int> */
    public static function eventVersions(): array
    {
        return self::EVENT_VERSIONS;
    }
}
