<?php

declare(strict_types=1);

namespace App\Addressing\RepositoryInterface;

interface AddressOutboxDispatchRepositoryInterface
{
    /** @return array<int, array<string, mixed>> */
    public function reserve(string $lockId, int $limit): array;

    public function markPublished(int $id): void;

    public function markDispatchFailure(int $id, ?string $error): void;
}
