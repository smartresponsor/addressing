<?php
declare(strict_types=1);

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

namespace App\Service\Address;

use App\ServiceInterface\Address\AddressOutboxDrainerInterface;
use PDO;

/**
 *
 */

/**
 *
 */
final class AddressOutboxDrainer implements AddressOutboxDrainerInterface
{
    /**
     * @param \PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param string $url
     * @param int $limit
     * @param int $retryLimit
     * @param int $timeoutSec
     * @param int $backoffMs
     * @return int
     */
    public function drain(string $url, int $limit, int $retryLimit, int $timeoutSec, int $backoffMs): int
    {
        $sel = $this->pdo->prepare(
            'SELECT id, event_name, event_version, payload '
            . 'FROM address_outbox WHERE published_at IS NULL '
            . 'ORDER BY id ASC LIMIT :lim'
        );
        $sel->bindValue(':lim', $limit, PDO::PARAM_INT);
        $sel->execute();

        $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($rows as $r) {
            $payload = json_decode((string)($r['payload'] ?? ''), true);
            if (!is_array($payload)) {
                $payload = null;
            }

            $err = null;
            $ok = $this->post(
                $url,
                [
                    'name' => (string)$r['event_name'],
                    'version' => (int)$r['event_version'],
                    'payload' => $payload,
                ],
                $retryLimit,
                $timeoutSec,
                $backoffMs,
                $err
            );

            if ($ok) {
                $upd = $this->pdo->prepare(
                    'UPDATE address_outbox '
                    . 'SET published_at = now(), published_attempt = published_attempt + 1, last_error = NULL '
                    . 'WHERE id = :id'
                );
                $upd->execute([':id' => $r['id']]);
            } else {
                $upd = $this->pdo->prepare(
                    'UPDATE address_outbox '
                    . 'SET published_attempt = published_attempt + 1, last_error = :err '
                    . 'WHERE id = :id'
                );
                $upd->execute([':id' => $r['id'], ':err' => $err]);
            }

            $count++;
        }

        return $count;
    }

    /**
     * @param string $url
     * @param array<string, mixed> $data
     * @param int $retryLimit
     * @param int $timeoutSec
     * @param int $backoffMs
     * @param string|null $error
     * @return bool
     */
    private function post(
        string $url,
        array $data,
        int $retryLimit,
        int $timeoutSec,
        int $backoffMs,
        ?string &$error
    ): bool {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            $error = 'json: encode failed';
            return false;
        }

        $attempt = 0;
        $error = null;

        while (true) {
            $attempt++;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_CONNECTTIMEOUT => $timeoutSec,
                CURLOPT_TIMEOUT => $timeoutSec,
            ]);

            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err !== '') {
                $error = 'curl: ' . $err;
            } elseif ($code >= 200 && $code < 300) {
                return true;
            } else {
                $error = 'http: ' . $code . ' body: ' . substr((string)$resp, 0, 500);
            }

            if ($attempt > $retryLimit) {
                return false;
            }

            // Linear backoff with growth.
            usleep($backoffMs * 1000 * $attempt);
        }
    }
}
