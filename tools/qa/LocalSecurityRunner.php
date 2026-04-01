<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

$command = $argv[1] ?? null;
if (!is_string($command) || '' === $command) {
    fwrite(STDERR, "Usage: php tools/qa/LocalSecurityRunner.php <gitleaks|semgrep-ce>\n");
    exit(2);
}

$projectRoot = dirname(__DIR__, 2);
chdir($projectRoot);

$exitCode = match ($command) {
    'gitleaks' => runGitleaks($projectRoot),
    'semgrep-ce' => runSemgrepCommunityEdition($projectRoot),
    default => 2,
};

if (2 === $exitCode) {
    fwrite(STDERR, "Unsupported security command: {$command}\n");
}

exit($exitCode);

function runGitleaks(string $projectRoot): int
{
    $binary = findBinary(['gitleaks']);
    if (null !== $binary) {
        return passthruWithExitCode(implode(' ', [
            escapeCommandSegment($binary),
            'git',
            '--redact',
            '--verbose',
            '--exit-code',
            '1',
            escapeCommandSegment($projectRoot),
        ]));
    }

    if (dockerImageAvailable('zricethezav/gitleaks:latest')) {
        return passthruWithExitCode(implode(' ', [
            'docker run --rm --pull=never',
            '-v',
            escapeCommandSegment($projectRoot.':/repo'),
            'zricethezav/gitleaks:latest',
            'git',
            '--redact',
            '--verbose',
            '--exit-code',
            '1',
            '/repo',
        ]));
    }

    fwrite(STDERR, "[security:gitleaks] gitleaks binary or pre-pulled Docker image is required.\n");

    return 1;
}

function runSemgrepCommunityEdition(string $projectRoot): int
{
    $targets = ['src', 'tests', 'config', 'public', 'bin', 'tools'];
    $targetArgs = implode(' ', array_map(static fn (string $path): string => escapeCommandSegment($path), $targets));
    $semgrepArgs = '--config p/php --config p/secrets --error '.$targetArgs;

    $binary = findBinary(['semgrep']);
    if (null !== $binary) {
        return passthruWithExitCode(escapeCommandSegment($binary).' scan '.$semgrepArgs);
    }

    if (dockerImageAvailable('returntocorp/semgrep:latest')) {
        $mountedTargets = implode(' ', array_map(static fn (string $path): string => '/src/'.$path, $targets));

        return passthruWithExitCode(implode(' ', [
            'docker run --rm --pull=never',
            '-v',
            escapeCommandSegment($projectRoot.':/src'),
            'returntocorp/semgrep:latest',
            'semgrep',
            'scan',
            '--config',
            'p/php',
            '--config',
            'p/secrets',
            '--error',
            $mountedTargets,
        ]));
    }

    fwrite(STDERR, "[security:semgrep-ce] semgrep binary or pre-pulled Docker image is required.\n");

    return 1;
}

function findBinary(array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        $probe = stripos(PHP_OS_FAMILY, 'Windows') === 0
            ? 'where '.escapeCommandSegment($candidate).' 2>NUL'
            : 'command -v '.escapeCommandSegment($candidate).' 2>/dev/null';

        exec($probe, $output, $exitCode);
        if (0 !== $exitCode || [] === $output) {
            continue;
        }

        $path = trim((string) $output[0]);
        if ('' !== $path) {
            return $path;
        }
    }

    return null;
}

function dockerImageAvailable(string $image): bool
{
    $nullDevice = stripos(PHP_OS_FAMILY, 'Windows') === 0 ? 'NUL' : '/dev/null';
    exec('docker image inspect '.escapeCommandSegment($image).' >'.$nullDevice.' 2>'.$nullDevice, $output, $exitCode);

    return 0 === $exitCode;
}

function passthruWithExitCode(string $command): int
{
    passthru($command, $exitCode);

    return $exitCode;
}

function escapeCommandSegment(string $value): string
{
    return escapeshellarg($value);
}
