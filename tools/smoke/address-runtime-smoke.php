<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'public/index.php' => is_file($root . '/public/index.php'),
    'src/Http/AddressApi/Controller.php' => is_file($root . '/src/Http/AddressApi/Controller.php'),
    'src/Repository/AddressRepository.php' => is_file($root . '/src/Repository/AddressRepository.php') || is_file($root . '/src/Repository/Address/AddressRepository.php'),
    'openapi/address.yaml' => is_file($root . '/openapi/address.yaml'),
];

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => $ok === false));

$report = [
    'component' => 'Addressing',
    'checks' => $checks,
    'status' => $failed === [] ? 'ok' : 'fail',
];

fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
exit($failed === [] ? 0 : 1);
