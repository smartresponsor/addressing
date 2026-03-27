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
final class SecurityHeaders
{
    /**
     * @return void
     */
    public static function apply(): void
    {
        $csp = getenv('CSP') ?: "default-src 'none'; frame-ancestors 'none'; base-uri 'none'";
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('X-DNS-Prefetch-Control: off');
        header('Content-Security-Policy: ' . $csp);
        if ((getenv('HSTS') ?: '1') === '1') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
