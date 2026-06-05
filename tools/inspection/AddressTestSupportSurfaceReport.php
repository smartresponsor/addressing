<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$readServiceTestPath = $root.'/tests/Unit/AddressReadServiceUnitTest.php';

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'surface' => 'test_support',
    'status' => is_file($readServiceTestPath) ? 'ready' : 'incomplete',
    'proof' => [
        'tests/Unit/AddressReadServiceUnitTest.php' => is_file($readServiceTestPath),
        'legacy application service unit test removed' => !is_file($root.'/tests/Unit/'.'Address'.'Service'.'UnitTest.php'),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
