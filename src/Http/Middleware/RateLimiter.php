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
        try {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS rate_limit (
                client TEXT NOT NULL,
                rkey TEXT NOT NULL,
                ts INTEGER NOT NULL,
                cnt INTEGER NOT NULL,
                PRIMARY KEY (client, rkey)
            )');
        } catch (Throwable $e) {}
    }

    /**
     * @param string $client
     * @param string $key
     * @return bool
     */
    public function check(string $client, string $key): bool
    {
        if (!$this->pdo) return true; // no DB — no rate limiting
        $now = time();
        $minWindow = $now - 60;

        $stmt = $this->pdo->prepare('SELECT ts, cnt FROM rate_limit WHERE client = :c AND rkey = :k');
        $stmt->execute([':c'=>$client, ':k'=>$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO rate_limit (client,rkey,ts,cnt) VALUES (:c,:k,:t,1)');
            $stmt->execute([':c'=>$client, ':k'=>$key, ':t'=>$now]);
            return true;
        }

        $ts = (int)$row['ts'];
        $cnt = (int)$row['cnt'];

        if ($ts < $minWindow) {
            // New window
            $stmt = $this->pdo->prepare('UPDATE rate_limit SET ts = :t, cnt = 1 WHERE client = :c AND rkey = :k');
            $stmt->execute([':t'=>$now, ':c'=>$client, ':k'=>$key]);
            return true;
        }

        // Allow burst over minute limit
        $effectiveLimit = $this->limitPerMinute + $this->burst;
        if ($cnt + 1 > $effectiveLimit) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE rate_limit SET cnt = :n WHERE client = :c AND rkey = :k');
        $stmt->execute([':n'=>$cnt + 1, ':c'=>$client, ':k'=>$key]);
        return true;
    }
}
