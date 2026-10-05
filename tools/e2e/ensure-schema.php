<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$command = sprintf(
    '%s %s doctrine:schema:create --env=dev --no-interaction',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($root.'/bin/console'),
);

passthru($command, $exitCode);
if (0 !== $exitCode) {
    throw new RuntimeException('address_e2e_schema_ensure_failed');
}
