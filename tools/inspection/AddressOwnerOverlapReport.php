<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$paths = ['src', 'tests', 'public'];
$result = [
    'component' => 'Addressing',
    'owner' => 'Marketing America Corp',
    'author' => 'Oleksandr Tishchenko <dev@smartresponsor.com>',
    'path_count' => 0,
    'paths' => [],
];

foreach ($paths as $path) {
    $absolute = $root . DIRECTORY_SEPARATOR . $path;
    if (!is_dir($absolute)) {
        continue;
    }
    $result['path_count']++;
    $result['paths'][] = $path;
}

fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
