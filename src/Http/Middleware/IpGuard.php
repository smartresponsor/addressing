<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Middleware;

/**
 *
 */

/**
 *
 */
final class IpGuard
{
    /** @return list<string> */
    private static function listFromEnv(string $name): array
    {
        $v = getenv($name);
        if ($v === false || trim($v) === '') {
            return [];
        }
        $parts = array_filter(array_map('trim', explode(',', $v)), fn($s): bool => $s !== '');
        return array_values($parts);
    }

    public static function allowed(string $ip, string $path): bool
    {
        $deny = self::listFromEnv('DENY_IPS');
        foreach ($deny as $d) if ($ip === $d) {
            return false;
        }

        $allow = self::listFromEnv('ALLOW_IPS');
        if ($allow !== [] && !in_array($ip, $allow, true)) {
            return false;
        }

        $allowPaths = self::listFromEnv('ALLOW_PATHS');
        if ($allowPaths !== []) {
            foreach ($allowPaths as $allowPath) {
                if (str_starts_with($path, $allowPath)) {
                    return true;
                }
            }
            return false;
        }
        return true;
    }
}
