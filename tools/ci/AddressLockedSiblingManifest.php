<?php

declare(strict_types=1);

/**
 * Emits the exact allowlisted sibling repositories required by a locked Addressing installation.
 * Runs before Composer; never executes package-provided code.
 */
function addressLockedSiblingManifest(string $lockPath): array
{
    $allowed = [
        'collectioning/collection' => ['Collectioning', 'collectioning'],
        'cruding/crud' => ['Cruding', 'cruding'],
        'failing/failure' => ['Failing', 'failing'],
        'interfacing/interface' => ['Interfacing', 'interfacing'],
        'objecting/object' => ['Objecting', 'objecting'],
        'tabling/table' => ['Tabling', 'tabling-'],
        'viewing/view' => ['Viewing', 'viewing'],
        'gating/gate' => ['Gating', 'gating'],
    ];

    $lock = json_decode((string) file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);
    $found = [];
    foreach (array_merge($lock['packages'], $lock['packages-dev']) as $package) {
        $name = $package['name'];
        $dist = $package['dist'] ?? [];
        if ('path' !== ($dist['type'] ?? null)) {
            if (isset($allowed[$name])) {
                throw new RuntimeException('expected_locked_path_package: '.$name);
            }
            continue;
        }

        if (!isset($allowed[$name]) || isset($found[$name])) {
            throw new RuntimeException('unexpected_or_duplicate_path_package: '.$name);
        }

        [$directory, $repository] = $allowed[$name];
        $ref = $dist['reference'] ?? null;
        if ('../'.$directory !== ($dist['url'] ?? null) || !is_string($ref) || 1 !== preg_match('/^[a-f0-9]{40}$/D', $ref)) {
            throw new RuntimeException('invalid_locked_path_package: '.$name);
        }

        $found[$name] = [$name, $directory, $repository, $ref];
    }

    if (array_diff_key($allowed, $found) !== []) {
        throw new RuntimeException('missing_locked_path_package');
    }

    ksort($found);

    return array_values($found);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    foreach (addressLockedSiblingManifest(dirname(__DIR__, 2).'/composer.lock') as $entry) {
        fwrite(STDOUT, implode("\t", $entry).PHP_EOL);
    }
}
