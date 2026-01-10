<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\Http\Middleware;

use PDO;
use Throwable;

/**
 *
 */

/**
 *
 */
final class RateLimiter
{
    private ?PDO $pdo;
    private int $limitPerMinute;
    private int $burst;

    /**
     * @param \PDO|null $pdo
     * @param int $limitPerMinute
     * @param int $burst
     */
    public function __construct(?PDO $pdo, int $limitPerMinute = 60, int $burst = 30)
    {
        $this->pdo = $pdo;
        $this->limitPerMinute = $limitPerMinute;
        $this->burst = $burst;
        if ($this->pdo) $this->init();
    }

    /**
     * @return void
     */
    private function init(): void
    {
        if ($this->pdo === null) {
            return;
        }
        try {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS rate_limit (
                client TEXT NOT NULL,
                rkey TEXT NOT NULL,
                ts INTEGER NOT NULL,
                cnt INTEGER NOT NULL,
                PRIMARY KEY (client, rkey)
            )');
        } catch (Throwable $e) {
        }
    }

    /**
     * @param string $client
     * @param string $key
     * @return bool
     */
    public function check(string $client, string $key): bool
    {
        if ($this->pdo === null) {
            return true; // no DB — no rate limiting
        }
        $pdo = $this->pdo;
        $now = time();
        $minWindow = $now - 60;

        $stmt = $this->prepare($pdo, 'SELECT ts, cnt FROM rate_limit WHERE client = :c AND rkey = :k');
        $stmt->execute([':c' => $client, ':k' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            $stmt = $this->prepare($pdo, 'INSERT OR REPLACE INTO rate_limit (client,rkey,ts,cnt) VALUES (:c,:k,:t,1)');
            $stmt->execute([':c' => $client, ':k' => $key, ':t' => $now]);
            return true;
        }

        /** @var array<string, mixed> $row */
        $ts = $this->asInt($row['ts'] ?? null);
        $cnt = $this->asInt($row['cnt'] ?? null);

        if ($ts < $minWindow) {
            // New window
            $stmt = $this->prepare($pdo, 'UPDATE rate_limit SET ts = :t, cnt = 1 WHERE client = :c AND rkey = :k');
            $stmt->execute([':t' => $now, ':c' => $client, ':k' => $key]);
            return true;
        }

        // Allow burst over minute limit
        $effectiveLimit = $this->limitPerMinute + $this->burst;
        if ($cnt + 1 > $effectiveLimit) {
            return false;
        }

        $stmt = $this->prepare($pdo, 'UPDATE rate_limit SET cnt = :n WHERE client = :c AND rkey = :k');
        $stmt->execute([':n' => $cnt + 1, ':c' => $client, ':k' => $key]);
        return true;
    }

    private function asInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int)$value;
        }
        if (is_float($value)) {
            return (int)$value;
        }
        return 0;
    }

    private function prepare(PDO $pdo, string $sql): \PDOStatement
    {
        $stmt = $pdo->prepare($sql);
        if ($stmt === false) {
            throw new \RuntimeException('prepare_failed');
        }
        return $stmt;
    }
}
