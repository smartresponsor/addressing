<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$composerPath = $root.'/composer.json';
if (!is_file($composerPath)) {
    throw new RuntimeException('composer_json_missing');
}

$payload = json_decode((string) file_get_contents($composerPath), true);
if (!is_array($payload) || !isset($payload['scripts']) || !is_array($payload['scripts'])) {
    throw new RuntimeException('composer_scripts_missing');
}

/** @var array<string, mixed> $scripts */
$scripts = $payload['scripts'];

$report = [];
$missingPaths = [];
foreach ($scripts as $name => $definition) {
    if (!is_string($name) || !(str_starts_with($name, 'smoke:') || str_starts_with($name, 'report:') || 'qa:trust-surface' === $name)) {
        continue;
    }

    $commands = [];
    $paths = [];
    flattenScriptDefinition($definition, $commands);

    foreach ($commands as $command) {
        preg_match_all(
            '#(?:(?:^|\s)(?:@php\s+)?(?:tools/php/php\d+\.(?:php|bat)\s+)?(?:php\s+)?)((?:\./)?(?:bin|config|public|src|tests|tools)/[^\s"\']+\.php)#',
            $command,
            $matches
        );

        foreach ($matches[1] as $path) {
            $normalized = ltrim($path, './');
            if (!in_array($normalized, $paths, true)) {
                $paths[] = $normalized;
            }
        }
    }

    $entries = [];
    foreach ($paths as $path) {
        $exists = is_file($root.'/'.$path);
        if (!$exists) {
            $missingPaths[] = $path;
        }

        $entries[] = [
            'path' => $path,
            'exists' => $exists,
        ];
    }

    $report[$name] = [
        'commands' => $commands,
        'paths' => $entries,
    ];
}

$missingPaths = array_values(array_unique($missingPaths));

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => [] === $missingPaths ? 'ready' : 'incomplete',
    'scriptCount' => count($report),
    'missingPathCount' => count($missingPaths),
    'missingPaths' => $missingPaths,
    'scripts' => $report,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

/**
 * @param mixed        $definition
 * @param list<string> $commands
 */
function flattenScriptDefinition(mixed $definition, array &$commands): void
{
    if (is_string($definition)) {
        $commands[] = $definition;

        return;
    }

    if (!is_array($definition)) {
        return;
    }

    foreach ($definition as $item) {
        flattenScriptDefinition($item, $commands);
    }
}
