<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

$count = 1;
$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../../bin/console').' address:demo:load --count='.escapeshellarg((string) $count);
passthru($command, $exitCode);

if (0 !== $exitCode) {
    throw new RuntimeException('fixture_load_smoke_failed');
}

fwrite(STDOUT, "fixture load smoke ok\n");
