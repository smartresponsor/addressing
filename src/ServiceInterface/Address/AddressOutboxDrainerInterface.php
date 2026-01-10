<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

namespace App\ServiceInterface\Address;

/**
 *
 */

/**
 *
 */
interface AddressOutboxDrainerInterface
{
    /**
     * @param string $url
     * @param int $limit
     * @param int $retryLimit
     * @param int $timeoutSec
     * @param int $backoffMs
     * @return int
     */
    public function drain(string $url, int $limit, int $retryLimit, int $timeoutSec, int $backoffMs): int;
}
