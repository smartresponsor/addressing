<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
namespace App\Http;

/**
 *
 */

/**
 *
 */
final class ErrorMap
{
    /**
     * @param int $status
     * @param string $code
     * @param array<string, mixed> $message
     * @param array<string, mixed> $meta
     * @return void
     */
    public static function emit(int $status, string $code, array $message, array $meta = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>['code'=>$code,'message'=>$message,'meta'=>$meta]], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }
}
