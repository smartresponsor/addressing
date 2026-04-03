<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$binary = $root.'/vendor/bin/phpmd';
$argv = $_SERVER['argv'] ?? [];
$target = $argv[1] ?? null;
$format = $argv[2] ?? 'text';
$ruleset = $argv[3] ?? 'phpmd.xml.dist';

if (!is_string($target) || '' === trim($target)) {
    fwrite(STDERR, "usage: php tools/qa/AddressPhpmdRunner.php <target> [format] [ruleset]".PHP_EOL);

    exit(64);
}


if (!function_exists('simplexml_load_string')) {
    fwrite(STDOUT, json_encode([
        'component' => 'Addressing',
        'tool' => 'phpmd',
        'target' => $target,
        'status' => 'blocked',
        'reason' => 'php_simplexml_extension_missing',
        'functionExpected' => 'simplexml_load_string',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    exit(2);
}

if (!is_file($binary)) {
    fwrite(STDOUT, json_encode([
        'component' => 'Addressing',
        'tool' => 'phpmd',
        'target' => $target,
        'status' => 'blocked',
        'reason' => 'vendor_phpmd_binary_missing',
        'binary' => 'vendor/bin/phpmd',
        'composerPackageExpected' => 'phpmd/phpmd',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    exit(2);
}

$command = [
    PHP_BINARY,
    $binary,
    $target,
    $format,
    $ruleset,
];

$descriptorSpec = [
    0 => STDIN,
    1 => STDOUT,
    2 => STDERR,
];

$process = proc_open($command, $descriptorSpec, $pipes, $root);
if (!is_resource($process)) {
    fwrite(STDERR, 'phpmd_process_start_failed'.PHP_EOL);

    exit(70);
}

$exitCode = proc_close($process);
if (!is_int($exitCode)) {
    fwrite(STDERR, 'phpmd_process_exit_code_unavailable'.PHP_EOL);

    exit(70);
}

exit($exitCode);
