<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$src = $root . '/src';
$map = [];

if (is_dir($src)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
            continue;
        }
        $basename = $fileInfo->getBasename('.php');
        $map[$basename][] = str_replace($root . DIRECTORY_SEPARATOR, '', $fileInfo->getPathname());
    }
}

$duplicates = array_filter($map, static fn (array $paths): bool => count($paths) > 1);
ksort($duplicates);

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'duplicate_class_basename' => $duplicates,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
