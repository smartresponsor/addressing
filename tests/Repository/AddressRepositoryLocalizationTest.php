<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace Tests\Repository;

use App\Entity\Address\AddressData;
use App\Repository\Address\AddressRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 *
 */
final class AddressRepositoryLocalizationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec(
            'CREATE TABLE address_entity (
                id CHAR(26) PRIMARY KEY,
                owner_id VARCHAR(64) NULL,
                vendor_id VARCHAR(64) NULL,
                line1 VARCHAR(256) NOT NULL,
                line2 VARCHAR(256) NULL,
                city VARCHAR(128) NOT NULL,
                region VARCHAR(128) NULL,
                postal_code VARCHAR(32) NULL,
                country_code CHAR(2) NOT NULL,
                line1_norm VARCHAR(256) NULL,
                city_norm VARCHAR(128) NULL,
                region_norm VARCHAR(128) NULL,
                postal_code_norm VARCHAR(32) NULL,
                latitude DOUBLE PRECISION NULL,
                longitude DOUBLE PRECISION NULL,
                geohash VARCHAR(32) NULL,
                validation_status VARCHAR(16) NOT NULL,
                validation_provider VARCHAR(64) NULL,
                validated_at TEXT NULL,
                dedupe_key VARCHAR(128) NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NULL,
                deleted_at TEXT NULL
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE address_outbox (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stream VARCHAR(64) NOT NULL DEFAULT \'address\',
                event_name VARCHAR(64) NOT NULL,
                event_version INT NOT NULL DEFAULT 1,
                payload TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                published_at TEXT NULL
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE address_localization (
                address_id CHAR(26) NOT NULL,
                locale VARCHAR(32) NOT NULL,
                line1 VARCHAR(256) NULL,
                city VARCHAR(128) NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                PRIMARY KEY (address_id, locale)
            )'
        );
    }

    public function testCreateAndFetchLocalizedMaps(): void
    {
        $repo = new AddressRepository($this->pdo);
        $line1Localized = [
            'ja-JP' => '東京都',
            'ru-RU' => '',
        ];
        $cityLocalized = [
            'ja-JP' => '港区',
            'fr-FR' => 'Paris',
        ];

        $address = $this->makeAddress($line1Localized, $cityLocalized);
        $repo->create($address);

        $fetched = $repo->get($address->id());

        static::assertNotNull($fetched);
        static::assertSame($line1Localized, $fetched->line1Localized());
        static::assertSame($cityLocalized, $fetched->cityLocalized());

        $localized = $repo->fetchLocalizations($address->id());
        static::assertSame($line1Localized, $localized['line1Localized']);
        static::assertSame($cityLocalized, $localized['cityLocalized']);
    }

    public function testReplaceLocalizationsClearsRows(): void
    {
        $repo = new AddressRepository($this->pdo);
        $address = $this->makeAddress(['en-US' => 'Main'], ['en-US' => 'City']);

        $repo->create($address);
        $repo->replaceLocalizations($address->id(), null, null);

        $localized = $repo->fetchLocalizations($address->id());
        static::assertNull($localized['line1Localized']);
        static::assertNull($localized['cityLocalized']);
    }

    public function testCreateRollsBackOnLocalizationError(): void
    {
        $repo = new AddressRepository($this->pdo);
        $address = $this->makeAddress(['en-US' => ['bad']], null);

        try {
            $repo->create($address);
            static::fail('Expected exception was not thrown.');
        } catch (\RuntimeException $e) {
            static::assertSame('invalid_line1Localized_value', $e->getMessage());
        }

        $stmt = $this->pdo->query('SELECT COUNT(*) AS cnt FROM address_entity');
        $count = (int) $stmt->fetchColumn();
        static::assertSame(0, $count);
    }

    /**
     * @param array<string, string>|null $line1Localized
     * @param array<string, string>|null $cityLocalized
     * @return \App\Entity\Address\AddressData
     */
    private function makeAddress(?array $line1Localized, ?array $cityLocalized): AddressData
    {
        $now = (new DateTimeImmutable('2025-01-01T00:00:00Z'))->format(DATE_ATOM);

        return new AddressData(
            '01J7Z7P3ZP6H1T4Q1X9G4J7YRA',
            null,
            null,
            'Main',
            null,
            'City',
            null,
            null,
            'US',
            $line1Localized,
            $cityLocalized,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'unknown',
            null,
            null,
            null,
            $now,
            null,
            null,
            null,
            null,
            null,
            null,
            null
        );
    }
}
