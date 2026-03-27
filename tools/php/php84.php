<?php

declare(strict_types=1);

$binary = match (PHP_OS_FAMILY) {
    'Windows' => 'C:\\PHP\\php-8.4.13-nts-Win32-vs17-x64\\php.exe',
    default => is_file('/usr/bin/php8.4') ? '/usr/bin/php8.4' : PHP_BINARY,
};

$args = $argv;
array_shift($args);

$command = escapeshellarg($binary);

foreach ($args as $arg) {
    $command .= ' ' . escapeshellarg($arg);
}

passthru($command, $exitCode);
exit($exitCode);
