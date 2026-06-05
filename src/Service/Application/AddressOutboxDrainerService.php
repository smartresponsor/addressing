<?php

declare(strict_types=1);

namespace App\Service\Application;

use App\Entity\AddressOutboxEntity;
use Doctrine\ORM\EntityManagerInterface;

final class AddressOutboxDrainerService
{
    /** @var callable(string, array<string, mixed>, int, int, int, ?string): bool|null */
    private $sender;

    public function __construct(private readonly EntityManagerInterface $entityManager, ?callable $sender = null)
    {
        $this->sender = $sender;
    }

    public function drain(string $url, int $limit = 100, int $retryLimit = 3, int $timeoutSec = 10, int $backoffMs = 250): int
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

    /** @return array<int, array<string, mixed>> */
    private function reserveRows(string $lockId, int $limit): array
    {
        $this->entityManager->beginTransaction();

        try {
            /** @var list<AddressOutboxEntity> $entities */
            $entities = $this->entityManager->getRepository(AddressOutboxEntity::class)->findBy(
                ['publishedAt' => null, 'lockedAt' => null],
                ['id' => 'ASC'],
                max(1, $limit),
            );

            if ([] === $entities) {
                $this->entityManager->commit();

                return [];
            }

            $rows = [];
            $now = new \DateTimeImmutable('now');
            foreach ($entities as $entity) {
                $entity->setLockedAt($now);
                $entity->setLockedBy($lockId);
                $rows[] = [
                    'id' => $entity->getId(),
                    'event_name' => $entity->getEventName(),
                    'event_version' => $entity->getEventVersion(),
                    'payload' => $entity->getPayload(),
                ];
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return $rows;
        } catch (\Throwable $throwable) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            throw $throwable;
        }
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
     * @return array{name:string, version:int, payload:array<string, mixed>|null}
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
        $entity = $this->entityManager->find(AddressOutboxEntity::class, $id);
        if (!$entity instanceof AddressOutboxEntity) {
            return;
        }

        $entity
            ->setPublishedAt(new \DateTimeImmutable('now'))
            ->setLockedAt(null)
            ->setLockedBy(null)
            ->setPublishedAttempt($entity->getPublishedAttempt() + 1)
            ->setLastError(null);

        $this->entityManager->flush();
    }

    private function markDispatchFailure(int $id, ?string $error): void
    {
        $entity = $this->entityManager->find(AddressOutboxEntity::class, $id);
        if (!$entity instanceof AddressOutboxEntity) {
            return;
        }

        $entity
            ->setLockedAt(null)
            ->setLockedBy(null)
            ->setPublishedAttempt($entity->getPublishedAttempt() + 1)
            ->setLastError($error);

        $this->entityManager->flush();
    }

    /** @param array<string, mixed> $data */
    private function send(AddressOutboxDispatchConfig $dispatchConfig, array $data, ?string &$error): bool
    {
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
    private function dispatchViaSender(callable $sender, AddressOutboxDispatchConfig $dispatchConfig, array $data, ?string &$error): bool
    {
        return $sender(
            $dispatchConfig->url,
            $data,
            $dispatchConfig->retryLimit,
            $dispatchConfig->timeoutSec,
            $dispatchConfig->backoffMs,
            $error,
        );
    }

    /** @param array<string, mixed> $data */
    private function encodedDispatchPayload(array $data, ?string &$error): ?string
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false !== $payload) {
            return $payload;
        }

        $error = 'json: encode failed';

        return null;
    }

    private function post(AddressOutboxDispatchConfig $dispatchConfig, string $payload, ?string &$error): bool
    {
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
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
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

        return 'http: '.$code.' '.(is_string($response) ? trim($response) : '');
    }

    private function shouldRetry(int $attempt, AddressOutboxDispatchConfig $dispatchConfig): bool
    {
        return $attempt <= $dispatchConfig->retryLimit;
    }

    private function retryDelayMicros(int $attempt, AddressOutboxDispatchConfig $dispatchConfig): int
    {
        return max(0, $dispatchConfig->backoffMs * $attempt) * 1000;
    }
}
