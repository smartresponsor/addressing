<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$composerPath = $projectRoot.'/composer.json';

if (!is_file($composerPath)) {
    throw new RuntimeException('composer_json_not_found');
}

$composerJson = file_get_contents($composerPath);
if (false === $composerJson) {
    throw new RuntimeException('composer_json_read_failed');
}

$decoded = json_decode($composerJson, true);
if (!is_array($decoded)) {
    throw new RuntimeException('composer_json_decode_failed');
}

$scripts = $decoded['scripts'] ?? null;
if (!is_array($scripts)) {
    throw new RuntimeException('composer_scripts_missing');
}

$rows = [];
foreach ($scripts as $name => $command) {
    if (!is_string($name) || !str_starts_with($name, 'smoke:')) {
        continue;
    }

    $commands = is_array($command) ? $command : [$command];
    foreach ($commands as $entry) {
        if (!is_string($entry)) {
            continue;
        }

        if (!preg_match('/([A-Za-z0-9_\/.-]+\.php)/', $entry, $matches)) {
            $rows[] = [
                'script' => $name,
                'path' => null,
                'exists' => null,
                'command' => $entry,
            ];
            continue;
        }

        $relativePath = $matches[1];
        $absolutePath = $projectRoot.'/'.$relativePath;
        $rows[] = [
            'script' => $name,
            'path' => $relativePath,
            'exists' => is_file($absolutePath),
            'command' => $entry,
        ];
    }
}

usort(
    $rows,
    static fn (array $left, array $right): int => [$left['script'] ?? '', $left['path'] ?? ''] <=> [$right['script'] ?? '', $right['path'] ?? '']
);

$missingCount = 0;
foreach ($rows as $row) {
    if (false === ($row['exists'] ?? null)) {
        ++$missingCount;
    }
}

$output = [
    'generatedAt' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'projectRoot' => $projectRoot,
    'smokeScriptCount' => count($rows),
    'missingPathCount' => $missingCount,
    'rows' => $rows,
];

$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (false === $json) {
    throw new RuntimeException('report_encode_failed');
}

fwrite(STDOUT, $json."\n");
