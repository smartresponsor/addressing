<?php

declare(strict_types=1);

$binary = match (PHP_OS_FAMILY) {
    'Windows' => 'C:\\PHP\\php-8.2.30-nts-Win32-vs16-x64\\php.exe',
    default => is_file('/usr/bin/php8.2') ? '/usr/bin/php8.2' : PHP_BINARY,
};

$args = $argv;
array_shift($args);

$command = escapeshellarg($binary);

foreach ($args as $arg) {
    $command .= ' ' . escapeshellarg($arg);
}

passthru($command, $exitCode);
exit($exitCode);
