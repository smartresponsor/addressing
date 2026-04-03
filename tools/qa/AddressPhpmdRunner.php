<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>|null
 */
function readComposerLock(string $root): ?array
{
    $lockPath = $root.'/composer.lock';
    if (!is_file($lockPath)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($lockPath), true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * @return string|null
 */
function packageVersion(?array $lock, string $package): ?string
{
    if (!is_array($lock)) {
        return null;
    }

    foreach (['packages', 'packages-dev'] as $section) {
        $entries = $lock[$section] ?? null;
        if (!is_array($entries)) {
            continue;
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (($entry['name'] ?? null) === $package && is_string($entry['version'] ?? null)) {
                return $entry['version'];
            }
        }
    }

    return null;
}

function normalizeVersion(string $version): string
{
    return ltrim($version, 'v');
}

function isPhpmdToolingIncompatible(?string $phpmdVersion, ?string $pdependVersion): bool
{
    if (!is_string($phpmdVersion) || !is_string($pdependVersion)) {
        return false;
    }

    return version_compare(normalizeVersion($phpmdVersion), '2.15.0', '<')
        || version_compare(normalizeVersion($pdependVersion), '2.16.1', '<');
}

/**
 * @param array<string, mixed> $payload
 */
function emitBlocked(array $payload): never
{
    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(2);
}

$root = dirname(__DIR__, 2);
$binary = $root.'/vendor/bin/phpmd';
$argv = $_SERVER['argv'] ?? [];
$target = $argv[1] ?? null;
$format = $argv[2] ?? 'text';
$ruleset = $argv[3] ?? 'phpmd.xml.dist';

if (!is_string($target) || '' === trim($target)) {
    fwrite(STDERR, "usage: php tools/qa/AddressPhpmdRunner.php <target> [format] [ruleset]".PHP_EOL);

    exit(64);
}

if (!function_exists('simplexml_load_string')) {
    emitBlocked([
        'component' => 'Addressing',
        'tool' => 'phpmd',
        'target' => $target,
        'status' => 'blocked',
        'reason' => 'php_simplexml_extension_missing',
        'functionExpected' => 'simplexml_load_string',
    ]);
}

if (!is_file($binary)) {
    emitBlocked([
        'component' => 'Addressing',
        'tool' => 'phpmd',
        'target' => $target,
        'status' => 'blocked',
        'reason' => 'vendor_phpmd_binary_missing',
        'binary' => 'vendor/bin/phpmd',
        'composerPackageExpected' => 'phpmd/phpmd',
    ]);
}

$lock = readComposerLock($root);
$phpmdVersion = packageVersion($lock, 'phpmd/phpmd');
$pdependVersion = packageVersion($lock, 'pdepend/pdepend');

if (isPhpmdToolingIncompatible($phpmdVersion, $pdependVersion)) {
    emitBlocked([
        'component' => 'Addressing',
        'tool' => 'phpmd',
        'target' => $target,
        'status' => 'blocked',
        'reason' => 'vendor_phpmd_tooling_incompatible_with_current_symfony_runtime',
        'phpmdVersion' => $phpmdVersion,
        'pdependVersion' => $pdependVersion,
        'minimumSupported' => [
            'phpmd/phpmd' => '2.15.0',
            'pdepend/pdepend' => '2.16.1',
        ],
        'suggestedComposerUpdate' => 'composer update phpmd/phpmd pdepend/pdepend --with-all-dependencies',
    ]);
}

$command = [
    PHP_BINARY,
    $binary,
    $target,
    $format,
    $ruleset,
];

$descriptorSpec = [
    0 => STDIN,
    1 => STDOUT,
    2 => STDERR,
];

$process = proc_open($command, $descriptorSpec, $pipes, $root);
if (!is_resource($process)) {
    fwrite(STDERR, 'phpmd_process_start_failed'.PHP_EOL);

    exit(70);
}

$exitCode = proc_close($process);
if (!is_int($exitCode)) {
    fwrite(STDERR, 'phpmd_process_exit_code_unavailable'.PHP_EOL);

    exit(70);
}

exit($exitCode);
