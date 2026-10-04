<?php

declare(strict_types=1);

namespace App\Addressing\Config\Application;

/**
 * Captures immutable delivery settings for one Addressing outbox drain operation.
 *
 * The drainer uses this value to keep endpoint, retry, timeout, and backoff policy
 * consistent across every reserved row processed by the same command invocation.
 */
final readonly class AddressOutboxDispatchConfig
{
    public function __construct(
        public string $url,
        public int $retryLimit,
        public int $timeoutSec,
        public int $backoffMs,
    ) {
    }
}
