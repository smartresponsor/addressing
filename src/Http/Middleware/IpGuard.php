<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Middleware;

final class IpGuard
{
    /** @return list<string> */
    private static function listFromEnv(string $name): array
    {
        $value = getenv($name);
        if (false === $value || '' === trim($value)) {
            return [];
        }
        $parts = array_filter(array_map('trim', explode(',', $value)), fn ($item): bool => '' !== $item);

        return array_values($parts);
    }

    public static function allowed(string $ip, string $path): bool
    {
        $deny = self::listFromEnv('DENY_IPS');
        if (array_any($deny, fn ($d) => $ip === $d)) {
            return false;
        }

        $allow = self::listFromEnv('ALLOW_IPS');
        if ([] !== $allow && !in_array($ip, $allow, true)) {
            return false;
        }

        $allowPaths = self::listFromEnv('ALLOW_PATHS');
        if ([] !== $allowPaths) {
            return array_any($allowPaths, fn ($allowPath) => str_starts_with($path, $allowPath));
        }

        return true;
    }
}
