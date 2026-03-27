<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Middleware;

use Symfony\Component\HttpFoundation\Request;

/**
 *
 */

/**
 *
 */
final class Cors
{
    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param string $method
     * @return void
     */
    public static function handle(Request $request, string $method): void
    {
        $allow = getenv('CORS_ALLOW_ORIGINS') ?: '*';
        $methods = getenv('CORS_ALLOW_METHODS') ?: 'GET,POST,OPTIONS';
        $headers = getenv('CORS_ALLOW_HEADERS') ?: 'Content-Type,Authorization,X-Request-Id';
        $creds = (getenv('CORS_ALLOW_CREDENTIALS') ?: '0') === '1';

        $origin = $request->headers->get('Origin');
        $origin = is_string($origin) && $origin !== '' ? $origin : null;

        if ($allow === '*') {
            header('Access-Control-Allow-Origin: *');
        } elseif ($origin !== null && self::isAllowed($origin, $allow)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: ' . $methods);
        header('Access-Control-Allow-Headers: ' . $headers);
        if ($creds) {
            header('Access-Control-Allow-Credentials: true');
        }

        if ($method === 'OPTIONS') {
            http_response_code(204);
            header('Content-Length: 0');
            exit;
        }
    }

    /**
     * @param string $origin
     * @param string $allowList
     * @return bool
     */
    private static function isAllowed(string $origin, string $allowList): bool
    {
        $items = array_filter(array_map('trim', explode(',', $allowList)), fn($s) => $s !== '');
        foreach ($items as $pat) {
            if ($pat === '*') {
                return true;
            }
            if (strcasecmp($origin, $pat) === 0) {
                return true;
            }
        }
        return false;
    }
}
