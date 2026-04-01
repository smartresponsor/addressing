<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Service;

use App\Service\Application\AddressOutboxDrainerService;
use PHPUnit\Framework\TestCase;

final class AddressOutboxDrainerTest extends TestCase
{
    public function testMultipleDrainersDoNotDoublePublish(): void
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'address-outbox-');
        static::assertNotFalse($dbFile);

        $pdo1 = new \PDO('sqlite:'.$dbFile);
        $pdo2 = new \PDO('sqlite:'.$dbFile);
        $pdo1->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo2->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo1->exec($this->schemaSql());
        $pdo1->exec(
            'INSERT INTO address_outbox (event_name, event_version, payload) VALUES '
            ."('AddressCreated', 1, '{\"id\":\"addr-1\"}'),"
            ."('AddressCreated', 1, '{\"id\":\"addr-2\"}')"
        );

        $published = [];

        $drainer2 = new AddressOutboxDrainerService(
            $pdo2,
            function (
                string $url,
                array $data,
                int $retryLimit,
                int $timeoutSec,
                int $backoffMs,
                ?string &$error,
            ) use (&$published): bool {
                $published[] = $data['payload']['id'] ?? null;

                return true;
            }
        );

        $drainer1 = new AddressOutboxDrainerService(
            $pdo1,
            function (
                string $url,
                array $data,
                int $retryLimit,
                int $timeoutSec,
                int $backoffMs,
                ?string &$error,
            ) use (&$published, $drainer2): bool {
                $published[] = $data['payload']['id'] ?? null;
                $drainer2->drain('http://example.test', 10, 0, 1, 0);

                return true;
            }
        );

        $drainer1->drain('http://example.test', 1, 0, 1, 0);

        sort($published);
        static::assertSame(['addr-1', 'addr-2'], $published);

        $rows = $pdo1->query('SELECT published_at FROM address_outbox ORDER BY id ASC')
            ->fetchAll(\PDO::FETCH_COLUMN);
        static::assertCount(2, $rows);
        static::assertNotEmpty($rows[0]);
        static::assertNotEmpty($rows[1]);
    }

    private function schemaSql(): string
    {
        return <<<'SQL'
CREATE TABLE address_outbox (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  stream TEXT NOT NULL DEFAULT 'address',
  event_name TEXT NOT NULL,
  event_version INTEGER NOT NULL,
  payload TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at TEXT NULL,
  locked_at TEXT NULL,
  locked_by TEXT NULL,
  published_attempt INTEGER NOT NULL DEFAULT 0,
  last_error TEXT NULL
);
SQL;
    }
}
