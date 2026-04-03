<?php

declare(strict_types=1);

namespace App\Integration\Persistence;

use App\Contract\Message\AddressOutboxEventContract;

final readonly class AddressOutboxWriter
{
    public function __construct(private \PDO $pdo)
    {
    }

    /** @param array<string, mixed> $payload */
    public function write(array $payload): void
    {
        $eventName = 'AddressValidatedApplied';
        $payloadJson = $this->encodePayload(AddressOutboxEventContract::decoratePayload($eventName, $payload));
        $payloadExpr = $this->isPgsql() ? ':payload::jsonb' : ':payload';

        $pdoStatement = $this->prepare(
            "INSERT INTO address_outbox (event_name, event_version, payload)
         VALUES (:name, :ver, {$payloadExpr})"
        );

        $pdoStatement->execute([
            ':name' => $eventName,
            ':ver' => AddressOutboxEventContract::eventVersion($eventName),
            ':payload' => $payloadJson,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function encodePayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            throw new \RuntimeException('payload_encode_failed');
        }

        return $json;
    }

    private function isPgsql(): bool
    {
        $driverAttr = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return is_string($driverAttr) && 'pgsql' === $driverAttr;
    }

    private function prepare(string $sql): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        if (false === $stmt) {
            throw new \RuntimeException('prepare_failed');
        }

        return $stmt;
    }
}
