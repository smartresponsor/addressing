<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\ServiceInterface\Application;

/**
 * Defines the application contract for dispatching persisted Addressing outbox events.
 */
interface AddressOutboxDrainerServiceInterface
{
    /** Dispatches a bounded outbox batch and returns the number of processed rows. */
    public function drain(string $url, int $limit, int $retryLimit, int $timeoutSec, int $backoffMs): int;
}
