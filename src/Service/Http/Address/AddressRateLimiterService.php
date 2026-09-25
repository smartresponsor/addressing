<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Http\Address;

use App\Addressing\RepositoryInterface\AddressRateLimitRepositoryInterface;

final readonly class AddressRateLimiterService
{
    public function __construct(private ?AddressRateLimitRepositoryInterface $addressRateLimitRepository, private int $limitPerMinute = 60, private int $burst = 30)
    {
    }

    public function check(string $client, string $key): bool
    {
        if (!$this->addressRateLimitRepository instanceof AddressRateLimitRepositoryInterface) {
            return true;
        }

        return $this->addressRateLimitRepository->checkAndIncrement(
            $client,
            $key,
            $this->limitPerMinute + $this->burst,
        );
    }
}
