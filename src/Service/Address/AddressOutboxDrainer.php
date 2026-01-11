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
     * @var callable|null
     */
    private $sender;

    /**
     * @param \PDO $pdo
     * @param callable|null $sender
     */
    public function __construct(private readonly PDO $pdo, ?callable $sender = null)
    {
        $this->sender = $sender;
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
        $lockId = bin2hex(random_bytes(16));
        $rows = $this->reserveRows($lockId, $limit);
        $count = 0;

        foreach ($rows as $r) {
            $payloadRaw = $this->scalarToString($r['payload'] ?? null);
            $payload = json_decode($payloadRaw, true);
            if (!is_array($payload)) {
                $payload = null;
            }

            $err = null;
            $ok = $this->send(
                $url,
                [
                    'name' => $this->scalarToString($r['event_name'] ?? null),
                    'version' => $this->scalarToInt($r['event_version'] ?? null),
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
                    . 'SET published_at = now(), locked_at = NULL, locked_by = NULL, '
                    . 'published_attempt = published_attempt + 1, last_error = NULL '
                    . 'WHERE id = :id'
                );
                $upd->execute([':id' => $r['id']]);
            } else {
                $upd = $this->pdo->prepare(
                    'UPDATE address_outbox '
                    . 'SET locked_at = NULL, locked_by = NULL, '
                    . 'published_attempt = published_attempt + 1, last_error = :err '
                    . 'WHERE id = :id'
                );
                $upd->execute([':id' => $r['id'], ':err' => $err]);
            }

            $count++;
        }

        return $count;
    }

    /**
     * @param string $lockId
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    private function reserveRows(string $lockId, int $limit): array
    {
        $driverAttr = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driverAttr) ? $driverAttr : '';

        if ($driver === 'pgsql') {
            $stmt = $this->pdo->prepare(
                'WITH cte AS ('
                . 'SELECT id FROM address_outbox '
                . 'WHERE published_at IS NULL AND locked_at IS NULL '
                . 'ORDER BY id ASC LIMIT :lim '
                . 'FOR UPDATE SKIP LOCKED'
                . ') '
                . 'UPDATE address_outbox '
                . 'SET locked_at = now(), locked_by = :lockedBy '
                . 'FROM cte '
                . 'WHERE address_outbox.id = cte.id '
                . 'RETURNING address_outbox.id, event_name, event_version, payload'
            );
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':lockedBy', $lockId);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->pdo->beginTransaction();

        $sel = $this->pdo->prepare(
            'SELECT id FROM address_outbox '
            . 'WHERE published_at IS NULL AND locked_at IS NULL '
            . 'ORDER BY id ASC LIMIT :lim'
        );
        $sel->bindValue(':lim', $limit, PDO::PARAM_INT);
        $sel->execute();
        $ids = $sel->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($ids) || $ids === []) {
            $this->pdo->commit();
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $upd = $this->pdo->prepare(
            'UPDATE address_outbox '
            . 'SET locked_at = CURRENT_TIMESTAMP, locked_by = ? '
            . 'WHERE locked_at IS NULL AND id IN (' . $placeholders . ')'
        );
        $upd->execute(array_merge([$lockId], $ids));

        $rows = $this->pdo->prepare(
            'SELECT id, event_name, event_version, payload '
            . 'FROM address_outbox WHERE locked_by = ? AND published_at IS NULL'
        );
        $rows->execute([$lockId]);
        $result = $rows->fetchAll(PDO::FETCH_ASSOC);
        $this->pdo->commit();

        return $result;
    }

    private function scalarToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string)$value;
        }
        return '';
    }

    private function scalarToInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int)$value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int)$value;
        }
        return 0;
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
    private function send(
        string  $url,
        array   $data,
        int     $retryLimit,
        int     $timeoutSec,
        int     $backoffMs,
        ?string &$error
    ): bool
    {
        if (is_callable($this->sender)) {
            return ($this->sender)($url, $data, $retryLimit, $timeoutSec, $backoffMs, $error);
        }

        return $this->post($url, $data, $retryLimit, $timeoutSec, $backoffMs, $error);
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
        string  $url,
        array   $data,
        int     $retryLimit,
        int     $timeoutSec,
        int     $backoffMs,
        ?string &$error
    ): bool
    {
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
