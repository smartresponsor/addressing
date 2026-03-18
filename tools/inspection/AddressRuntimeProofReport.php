<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'composer.json' => is_file($root . '/composer.json'),
    'phpunit.xml.dist' => is_file($root . '/phpunit.xml.dist'),
    '.php-cs-fixer.dist.php' => is_file($root . '/.php-cs-fixer.dist.php'),
    'phpstan.neon.dist' => is_file($root . '/phpstan.neon.dist'),
    'phpmd.xml.dist' => is_file($root . '/phpmd.xml.dist'),
    'tools/qa/AddressPhpLint.php' => is_file($root . '/tools/qa/AddressPhpLint.php'),
    'tools/smoke/address-runtime-smoke.php' => is_file($root . '/tools/smoke/address-runtime-smoke.php'),
];

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => in_array(false, $checks, true) ? 'incomplete' : 'ready',
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
