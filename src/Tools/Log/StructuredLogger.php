<?php
declare(strict_types=1);
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only.
 * No placeholders or stubs.
 */

/**
 *
 */
final class StructuredLogger
{
    private const MAX_LINE_BYTES = 262144; // 256 KB hard safety limit

    private string $path;

    /**
     * @param string|null $path
     */
    public function __construct(?string $path = null)
    {
        $this->path = $path ?: (__DIR__ . '/address-api.ndjson');
    }

    /**
     * Writes a structured event to the log.
     *
     * Absolute guarantees:
     * - never throws
     * - never blocks business execution
     * - one event equals one line
     */
    public function log(array $event): void
    {
        $event = $this->normalize($event);

        $line = $this->encode($event);

        if (\strlen($line) > self::MAX_LINE_BYTES) {
            $line = $this->oversizeFallback($event);
        }

        $this->append($line);
    }

    /**
     * @param array $event
     * @return array
     */
    private function normalize(array $event): array
    {
        if (!isset($event['ts']) || !is_string($event['ts']) || $event['ts'] === '') {
            $event['ts'] = (new DateTimeImmutable())->format(DATE_ATOM);
        }

        return $event;
    }

    /**
     * @param array $event
     * @return string
     */
    private function encode(array $event): string
    {
        try {
            return json_encode(
                    $event,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                ) . "\n";
        } catch (JsonException) {
            return $this->encodeFailureFallback($event);
        }
    }

    /**
     * @param array $event
     * @return string
     */
    private function encodeFailureFallback(array $event): string
    {
        return '{"ts":"' . $event['ts'] . '","_error":"json_encode_failed"}' . "\n";
    }

    /**
     * @param array $event
     * @return string
     */
    private function oversizeFallback(array $event): string
    {
        return '{"ts":"' . $event['ts'] . '","_error":"event_too_large"}' . "\n";
    }

    /**
     * @param string $line
     * @return void
     */
    private function append(string $line): void
    {
        try {
            $written = file_put_contents(
                $this->path,
                $line,
                FILE_APPEND | LOCK_EX
            );

            // Intentionally ignored:
            // logging is best-effort and must never affect execution.
        } catch (Throwable) {
            // intentionally ignored
        }
    }
}
