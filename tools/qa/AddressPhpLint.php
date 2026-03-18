<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$targets = [
    $root . DIRECTORY_SEPARATOR . 'src',
    $root . DIRECTORY_SEPARATOR . 'tests',
    $root . DIRECTORY_SEPARATOR . 'public',
    $root . DIRECTORY_SEPARATOR . 'bin',
    $root . DIRECTORY_SEPARATOR . 'tool',
    $root . DIRECTORY_SEPARATOR . 'tools',
];

$phpFiles = [];
foreach ($targets as $target) {
    if (!is_dir($target)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }

        $path = $fileInfo->getPathname();
        if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
            continue;
        }

        if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
            continue;
        }

        $phpFiles[] = $path;
    }
}

sort($phpFiles);
$php = PHP_BINARY;
$errors = [];

foreach ($phpFiles as $file) {
    $command = sprintf('%s -l %s 2>&1', escapeshellarg($php), escapeshellarg($file));
    exec($command, $output, $exitCode);

    if ($exitCode === 0) {
        continue;
    }

    $errors[$file] = implode(PHP_EOL, $output);
}

if ($errors === []) {
    fwrite(STDOUT, sprintf("Addressing PHP lint passed for %d file(s).%s", count($phpFiles), PHP_EOL));
    exit(0);
}

foreach ($errors as $file => $message) {
    fwrite(STDERR, sprintf("[lint] %s%s%s%s", $file, PHP_EOL, $message, PHP_EOL));
}

exit(1);
