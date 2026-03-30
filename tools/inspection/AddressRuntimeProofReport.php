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
    'tools/smoke/category-runtime-smoke.php' => is_file($root . '/tools/smoke/category-runtime-smoke.php'),
    'tools/smoke/category-fixture-sanity.php' => is_file($root . '/tools/smoke/category-fixture-sanity.php'),
    'tools/smoke/category-container-boot-smoke.php' => is_file($root . '/tools/smoke/category-container-boot-smoke.php'),
    'tools/smoke/category-fixture-load-smoke.php' => is_file($root . '/tools/smoke/category-fixture-load-smoke.php'),
];

$missingChecks = array_keys(array_filter($checks, static fn (bool $exists): bool => false === $exists));

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => [] === $missingChecks ? 'ready' : 'incomplete',
    'missingCheckCount' => count($missingChecks),
    'missingChecks' => $missingChecks,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
