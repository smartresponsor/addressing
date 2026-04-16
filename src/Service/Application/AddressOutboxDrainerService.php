<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
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

    #[\Override]
    public function drain(string $url, int $limit, int $retryLimit, int $timeoutSec, int $backoffMs): int
    {
        $dispatchConfig = new AddressOutboxDispatchConfig(
            url: $url,
            retryLimit: $retryLimit,
            timeoutSec: $timeoutSec,
            backoffMs: $backoffMs,
        );
        $lockId = $this->lockId($url);
        $rows = $this->reserveRows($lockId, $limit);
        $count = 0;

        foreach ($rows as $row) {
            $this->dispatchReservedRow($dispatchConfig, $row);
            ++$count;
        }

        return $count;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reserveRows(string $lockId, int $limit): array
    {
        return 'pgsql' === $this->driver()
            ? $this->reservePgsqlRows($lockId, $limit)
            : $this->reserveGenericRows($lockId, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reservePgsqlRows(string $lockId, int $limit): array
    {
        $statement = $this->pdo->prepare(
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
        $statement->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $statement->bindValue(':lockedBy', $lockId);
        $statement->execute();

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reserveGenericRows(string $lockId, int $limit): array
    {
        $this->pdo->beginTransaction();

        $ids = $this->selectUnlockedIds($limit);
        if ([] === $ids) {
            $this->pdo->commit();

            return [];
        }

        $this->lockSelectedRows($lockId, $ids);
        $result = $this->lockedRowsByLockId($lockId);
        $this->pdo->commit();

        return $result;
    }

    /** @return list<int|string> */
    private function selectUnlockedIds(int $limit): array
    {
        $select = $this->pdo->prepare(
            'SELECT id FROM address_outbox '
            .'WHERE published_at IS NULL AND locked_at IS NULL '
            .'ORDER BY id ASC LIMIT :lim'
        );
        $select->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $select->execute();

        /** @var list<int|string> $ids */
        $ids = $select->fetchAll(\PDO::FETCH_COLUMN);

        return $ids;
    }

    /** @param list<int|string> $ids */
    private function lockSelectedRows(string $lockId, array $ids): void
    {
        $update = $this->pdo->prepare(
            'UPDATE address_outbox '
            .'SET locked_at = CURRENT_TIMESTAMP, locked_by = ? '
            .'WHERE locked_at IS NULL AND id IN ('.$this->positionalPlaceholders(count($ids)).')'
        );
        $update->execute(array_merge([$lockId], $ids));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lockedRowsByLockId(string $lockId): array
    {
        $rows = $this->pdo->prepare(
            'SELECT id, event_name, event_version, payload '
            .'FROM address_outbox WHERE locked_by = ? AND published_at IS NULL'
        );
        $rows->execute([$lockId]);

        /** @var array<int, array<string, mixed>> $result */
        $result = $rows->fetchAll(\PDO::FETCH_ASSOC);

        return $result;
    }

    private function positionalPlaceholders(int $count): string
    {
        return implode(',', array_fill(0, $count, '?'));
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
    private function rowString(array $row, string $key): ?string
    {
        return isset($row[$key]) && is_string($row[$key]) ? $row[$key] : null;
    }

    /** @param array<string, mixed> $row */
    private function rowInt(array $row, string $key): int
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

    private function lockId(string $url): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return hash('sha256', uniqid($url, true));
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function eventPayload(array $row): array
    {
        $payload = json_decode($this->rowString($row, 'payload') ?? '', true);
        if (!is_array($payload)) {
            $payload = null;
        }

        return [
            'name' => $this->rowString($row, 'event_name') ?? '',
            'version' => $this->rowInt($row, 'event_version'),
            'payload' => $payload,
        ];
    }

    /** @param array<string, mixed> $row */
    private function dispatchReservedRow(AddressOutboxDispatchConfig $dispatchConfig, array $row): void
    {
        $error = null;
        $rowId = $this->rowInt($row, 'id');
        $ok = $this->send($dispatchConfig, $this->eventPayload($row), $error);

        if ($ok) {
            $this->markPublished($rowId);

            return;
        }

        $this->markDispatchFailure($rowId, $error);
    }

    private function markPublished(int $id): void
    {
        $update = $this->pdo->prepare(
            'UPDATE address_outbox '
            .'SET published_at = '.$this->currentTimestampSql().', locked_at = NULL, locked_by = NULL, '
            .'published_attempt = published_attempt + 1, last_error = NULL '
            .'WHERE id = :id'
        );
        $update->execute([':id' => $id]);
    }

    private function markDispatchFailure(int $id, ?string $error): void
    {
        $update = $this->pdo->prepare(
            'UPDATE address_outbox '
            .'SET locked_at = NULL, locked_by = NULL, '
            .'published_attempt = published_attempt + 1, last_error = :error '
            .'WHERE id = :id'
        );
        $update->execute([':id' => $id, ':error' => $error]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function send(
        AddressOutboxDispatchConfig $dispatchConfig,
        array $data,
        ?string &$error,
    ): bool {
        if (is_callable($this->sender)) {
            return $this->dispatchViaSender($this->sender, $dispatchConfig, $data, $error);
        }

        $payload = $this->encodedDispatchPayload($data, $error);
        if (null === $payload) {
            return false;
        }

        return $this->post($dispatchConfig, $payload, $error);
    }

    /**
     * @param callable(string, array<string, mixed>, int, int, int, ?string): bool $sender
     * @param array<string, mixed>                                                 $data
     */
    private function dispatchViaSender(
        callable $sender,
        AddressOutboxDispatchConfig $dispatchConfig,
        array $data,
        ?string &$error,
    ): bool {
        return $sender(
            $dispatchConfig->url,
            $data,
            $dispatchConfig->retryLimit,
            $dispatchConfig->timeoutSec,
            $dispatchConfig->backoffMs,
            $error,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encodedDispatchPayload(array $data, ?string &$error): ?string
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false !== $payload) {
            return $payload;
        }

        $error = 'json: encode failed';

        return null;
    }

    private function post(
        AddressOutboxDispatchConfig $dispatchConfig,
        string $payload,
        ?string &$error,
    ): bool {
        $attempt = 0;
        $error = null;

        while (true) {
            ++$attempt;

            $curlHandle = $this->curlHandle($dispatchConfig, $payload, $error);
            if (null === $curlHandle) {
                return false;
            }

            $response = curl_exec($curlHandle);
            $code = (int) curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curlHandle);
            curl_close($curlHandle);

            if ($this->isSuccessfulHttpCode($code, $curlError)) {
                return true;
            }

            $error = $this->dispatchFailureMessage($code, $curlError, $response);
            if (!$this->shouldRetry($attempt, $dispatchConfig)) {
                return false;
            }

            usleep($this->retryDelayMicros($attempt, $dispatchConfig));
        }
    }

    private function curlHandle(AddressOutboxDispatchConfig $dispatchConfig, string $payload, ?string &$error): ?\CurlHandle
    {
        $curlHandle = curl_init($dispatchConfig->url);
        if (!$curlHandle instanceof \CurlHandle) {
            $error = 'curl: init failed';

            return null;
        }

        curl_setopt_array($curlHandle, $this->curlOptions($dispatchConfig, $payload));

        return $curlHandle;
    }

    /** @return array<int, mixed> */
    private function curlOptions(AddressOutboxDispatchConfig $dispatchConfig, string $payload): array
    {
        return [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => $dispatchConfig->timeoutSec,
            CURLOPT_TIMEOUT => $dispatchConfig->timeoutSec,
        ];
    }

    private function isSuccessfulHttpCode(int $code, string $curlError): bool
    {
        return '' === $curlError && $code >= 200 && $code < 300;
    }

    private function dispatchFailureMessage(int $code, string $curlError, mixed $response): string
    {
        if ('' !== $curlError) {
            return 'curl: '.$curlError;
        }

        return 'http: '.$code.' body: '.substr($this->stringResponse($response), 0, 500);
    }

    private function stringResponse(mixed $response): string
    {
        if (is_string($response)) {
            return $response;
        }
        if (is_int($response) || is_float($response) || is_bool($response)) {
            return (string) $response;
        }

        return '';
    }

    private function shouldRetry(int $attempt, AddressOutboxDispatchConfig $dispatchConfig): bool
    {
        return $attempt <= $dispatchConfig->retryLimit;
    }

    private function retryDelayMicros(int $attempt, AddressOutboxDispatchConfig $dispatchConfig): int
    {
        return $dispatchConfig->backoffMs * 1000 * $attempt;
    }
}
