<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\ServiceInterface\Application;

interface AddressOutboxDrainerServiceInterface
{
    public function drain(string $url, int $limit, int $retryLimit, int $timeoutSec, int $backoffMs): int;
}
