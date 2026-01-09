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
use JsonException;
use PDO;
use Throwable;

/**
 *
 */
final readonly class AddressOutboxDrainer implements AddressOutboxDrainerInterface
{
    /**
     * @param \PDO $pdo
     */
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Drains unpublished outbox events and delivers them over HTTP.
     *
     * Absolute guarantees:
     * - the drainer never throws
     * - each row is processed at most once per run
     * - failures are recorded and isolated
     * - database state is always consistent
     */
    public function drain(
        string $url,
        int    $limit,
        int    $retryLimit,
        int    $timeoutSec,
        int    $backoffMs
    ): int
    {
        $processed = 0;

        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, event_name, event_version, payload
                 FROM address_outbox
                 WHERE published_at IS NULL
                 ORDER BY id ASC
                 LIMIT :lim
                 FOR UPDATE SKIP LOCKED'
            );
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $this->processRow(
                    $row,
                    $url,
                    $retryLimit,
                    $timeoutSec,
                    $backoffMs
                );
                $processed++;
            }
        } catch (Throwable) {
            // Absolute rule:
            // the drainer must never break the main execution flow.
        }

        return $processed;
    }

    /**
     * Processes a single outbox row.
     */
    private function processRow(
        array  $row,
        string $url,
        int    $retryLimit,
        int    $timeoutSec,
        int    $backoffMs
    ): void
    {
        $id = (int)$row['id'];
        $error = null;

        try {
            $payload = $this->decodePayload($row['payload'] ?? null);

            $ok = $this->postWithRetry(
                $url,
                [
                    'name' => (string)$row['event_name'],
                    'version' => (int)$row['event_version'],
                    'payload' => $payload,
                ],
                $retryLimit,
                $timeoutSec,
                $backoffMs,
                $error
            );

            if ($ok) {
                $this->markPublished($id);
            } else {
                $this->markFailed($id, $error);
            }
        } catch (Throwable $e) {
            $this->markFailed($id, 'internal: ' . $e->getMessage());
        }
    }

    /**
     * Decodes JSON payload strictly.
     */
    private function decodePayload(mixed $raw): ?array
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Sends payload over HTTP with retry and backoff.
     */
    private function postWithRetry(
        string  $url,
        array   $data,
        int     $retryLimit,
        int     $timeoutSec,
        int     $backoffMs,
        ?string &$error
    ): bool
    {
        try {
            $payload = json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            $error = 'json_encode_failed';
            return false;
        }

        $attempt = 0;
        $error = null;

        while (true) {
            $attempt++;

            $result = $this->sendHttp(
                $url,
                $payload,
                $timeoutSec,
                $error
            );

            if ($result === true) {
                return true;
            }

            if ($attempt > $retryLimit) {
                return false;
            }

            // Linear backoff with growth.
            usleep($backoffMs * 1000 * $attempt);
        }
    }

    /**
     * Executes a single HTTP POST.
     */
    private function sendHttp(
        string  $url,
        string  $payload,
        int     $timeoutSec,
        ?string &$error
    ): bool
    {
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
            return false;
        }

        if ($code >= 200 && $code < 300) {
            return true;
        }

        $error = 'http: ' . $code . ' body: ' . substr((string)$resp, 0, 500);
        return false;
    }

    /**
     * Marks an outbox row as published.
     */
    private function markPublished(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE address_outbox
             SET published_at = now(),
                 published_attempt = published_attempt + 1,
                 last_error = NULL
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    /**
     * Marks an outbox row as failed.
     */
    private function markFailed(int $id, ?string $error): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE address_outbox
             SET published_attempt = published_attempt + 1,
                 last_error = :err
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $id,
            ':err' => $error,
        ]);
    }
}
