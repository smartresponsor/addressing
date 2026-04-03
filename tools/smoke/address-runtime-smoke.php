<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'public/index.php' => is_file($root.'/public/index.php'),
    'src/Http/Controller/AddressController.php' => is_file($root.'/src/Http/Controller/AddressController.php'),
    'src/Repository/Persistence/AddressRepository.php' => is_file($root.'/src/Repository/Persistence/AddressRepository.php'),
    'tools/support/AddressRuntimeBootstrap.php' => is_file($root.'/tools/support/AddressRuntimeBootstrap.php'),
    'openapi/address.yaml' => is_file($root.'/openapi/address.yaml'),
];

$failed = array_keys(array_filter($checks, static fn (bool $ok): bool => $ok === false));
$ok = $failed === [];

$report = [
    'component' => 'Addressing',
    'checks' => $checks,
    'status' => $ok ? 'ready' : 'fail',
];

fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
exit($ok ? 0 : 1);
