<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http;

final class ErrorMap
{
    /**
     * @param array<string, mixed> $meta
     */
    public static function emit(int $status, string $code, string $message, array $meta = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $payload = [
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'meta' => $meta,
            ],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            http_response_code(500);
            echo '{"ok":false,"error":{"code":"encoding_failed","message":"encoding_failed","meta":[]}}';

            return;
        }

        echo $json;
    }
}
