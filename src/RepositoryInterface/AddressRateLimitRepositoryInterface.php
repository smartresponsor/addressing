<?php

declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

interface AddressRateLimitRepositoryInterface
{
    public function checkAndIncrement(string $client, string $key, int $effectiveLimit): bool;
}
