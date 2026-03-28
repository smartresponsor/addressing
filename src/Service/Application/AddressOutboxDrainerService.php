<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);


namespace App\Service\Application;

use App\ServiceInterface\Application\AddressOutboxDrainerServiceInterface;

final class AddressOutboxDrainerService implements AddressOutboxDrainerServiceInterface
{
    /**
     * @var callable|null
     */
    private $sender;

    public function __construct(private readonly \PDO $pdo, ?callable $sender = null)
    {
        $this->sender = $sender;
    }

    public function drain(string $url, int $limit, int $retryLimit, int $timeoutSec, int $backoffMs): int
    {
        $lockId = bin2hex(random_bytes(16));
        $rows = $this->reserveRows($lockId, $limit);
        $count = 0;

        foreach ($rows as $r) {
            $payload = json_decode(self::rowString($r, 'payload') ?? '', true);
            if (!is_array($payload)) {
                $payload = null;
            }

            $err = null;
            $ok = $this->send(
                $url,
                [
                    'name' => self::rowString($r, 'event_name') ?? '',
                    'version' => self::rowInt($r, 'event_version'),
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
                    .'SET published_at = '.$this->currentTimestampSql().', locked_at = NULL, locked_by = NULL, '
                    .'published_attempt = published_attempt + 1, last_error = NULL '
                    .'WHERE id = :id'
                );
                $upd->execute([':id' => self::rowInt($r, 'id')]);
            } else {
                $upd = $this->pdo->prepare(
                    'UPDATE address_outbox '
                    .'SET locked_at = NULL, locked_by = NULL, '
                    .'published_attempt = published_attempt + 1, last_error = :err '
                    .'WHERE id = :id'
                );
                $upd->execute([':id' => self::rowInt($r, 'id'), ':err' => $err]);
            }

            ++$count;
        }

        return $count;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reserveRows(string $lockId, int $limit): array
    {
        $driver = $this->driver();

        if ('pgsql' === $driver) {
            $stmt = $this->pdo->prepare(
                'WITH cte AS ('
                .'SELECT id FROM address_outbox '
                .'WHERE published_at IS NULL AND locked_at IS NULL '
                .'ORDER BY id ASC LIMIT :lim '
                .'FOR UPDATE SKIP LOCKED'
                .') '
                .'UPDATE address_outbox '
                .'SET locked_at = now(), locked_by = :lockedBy '
                .'FROM cte '
                .'WHERE address_outbox.id = cte.id '
                .'RETURNING address_outbox.id, event_name, event_version, payload'
            );
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':lockedBy', $lockId);
            $stmt->execute();

            /** @var array<int, array<string, mixed>> $rows */
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $rows;
        }

        $this->pdo->beginTransaction();

        $sel = $this->pdo->prepare(
            'SELECT id FROM address_outbox '
            .'WHERE published_at IS NULL AND locked_at IS NULL '
            .'ORDER BY id ASC LIMIT :lim'
        );
        $sel->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $sel->execute();
        $ids = $sel->fetchAll(\PDO::FETCH_COLUMN);

        if ([] === $ids) {
            $this->pdo->commit();

            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $upd = $this->pdo->prepare(
            'UPDATE address_outbox '
            .'SET locked_at = CURRENT_TIMESTAMP, locked_by = ? '
            .'WHERE locked_at IS NULL AND id IN ('.$placeholders.')'
        );
        $upd->execute(array_merge([$lockId], $ids));

        $rows = $this->pdo->prepare(
            'SELECT id, event_name, event_version, payload '
            .'FROM address_outbox WHERE locked_by = ? AND published_at IS NULL'
        );
        $rows->execute([$lockId]);
        /** @var array<int, array<string, mixed>> $result */
        $result = $rows->fetchAll(\PDO::FETCH_ASSOC);
        $this->pdo->commit();

        return $result;
    }

    private function currentTimestampSql(): string
    {
        $driver = $this->driver();

        return 'pgsql' === $driver ? 'now()' : 'CURRENT_TIMESTAMP';
    }

    private function driver(): string
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return is_string($driver) ? $driver : '';
    }

    /** @param array<string, mixed> $row */
    private static function rowString(array $row, string $key): ?string
    {
        return isset($row[$key]) && is_string($row[$key]) ? $row[$key] : null;
    }

    /** @param array<string, mixed> $row */
    private static function rowInt(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function send(
        string $url,
        array $data,
        int $retryLimit,
        int $timeoutSec,
        int $backoffMs,
        ?string &$error,
    ): bool {
        if (is_callable($this->sender)) {
            return ($this->sender)($url, $data, $retryLimit, $timeoutSec, $backoffMs, $error);
        }

        return $this->post($url, $data, $retryLimit, $timeoutSec, $backoffMs, $error);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function post(
        string $url,
        array $data,
        int $retryLimit,
        int $timeoutSec,
        int $backoffMs,
        ?string &$error,
    ): bool {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $payload) {
            $error = 'json: encode failed';

            return false;
        }

        $attempt = 0;
        $error = null;

        while (true) {
            ++$attempt;

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
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ('' !== $err) {
                $error = 'curl: '.$err;
            } elseif ($code >= 200 && $code < 300) {
                return true;
            } else {
                $error = 'http: '.$code.' body: '.substr((string) $resp, 0, 500);
            }

            if ($attempt > $retryLimit) {
                return false;
            }

            // Linear backoff with growth.
            usleep($backoffMs * 1000 * $attempt);
        }
    }
}
