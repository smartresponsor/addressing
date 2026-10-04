<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Middleware;

/**
 * Ensures each standalone Addressing request has a stable request identifier propagated to the response.
 */
final class AddressRequestIdMiddleware
{
    /**
     * Return the incoming request identifier or generate and publish a new one.
     *
     * @throws \Exception
     */
    public static function ensure(): string
    {
        $id = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(12));
        header('X-Request-Id: '.$id);

        return $id;
    }
}
