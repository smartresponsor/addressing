<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
namespace App\Http\Middleware;

final class RequestId
{
    public static function ensure(): string
    {
        $id = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(12));
        header('X-Request-Id: ' . $id);
        return $id;
    }
}
