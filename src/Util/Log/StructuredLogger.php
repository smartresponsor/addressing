<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Util\Log;

final readonly class StructuredLogger
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?: (__DIR__.'/address-api.ndjson');
    }

    /**
     * @param array<string, mixed> $event
     */
    public function log(array $event): void
    {
        $event['ts'] ??= (new \DateTimeImmutable('now'))->format(DATE_ATOM);
        $line = json_encode($event, JSON_UNESCAPED_UNICODE);
        if (false === $line) {
            $line = json_encode(
                ['ts' => $event['ts'], 'error' => 'encode_failed'],
                JSON_UNESCAPED_UNICODE
            );
        }
        if (false === $line) {
            return;
        }
        $line .= "\n";
        @file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }
}
