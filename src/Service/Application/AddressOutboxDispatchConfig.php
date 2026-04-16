<?php

declare(strict_types=1);

namespace App\Service\Application;

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
