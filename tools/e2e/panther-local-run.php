<?php
declare(strict_types=1);

function addressingProjectRoot(): string
{
    return dirname(__DIR__, 2);
}

function addressingPhp84Binary(): string
{
    return match (PHP_OS_FAMILY) {
        'Windows' => 'C:\\PHP\\php-8.4.13-nts-Win32-vs17-x64\\php.exe',
        default => is_file('/usr/bin/php8.4') ? '/usr/bin/php8.4' : PHP_BINARY,
    };
}

function addressingFindChromeDriverBinary(string $projectRoot): ?string
{
    $configured = getenv('PANTHER_CHROME_DRIVER_BINARY') ?: ($_SERVER['PANTHER_CHROME_DRIVER_BINARY'] ?? null);
    if (is_string($configured) && $configured !== '' && is_file($configured)) {
        $resolved = realpath($configured);

        return false === $resolved ? $configured : $resolved;
    }

    $candidates = [
        $projectRoot.'/bin/chromedriver.exe',
        $projectRoot.'/bin/chromedriver',
        $projectRoot.'/drivers/chromedriver.exe',
        $projectRoot.'/drivers/chromedriver',
        $projectRoot.'/vendor/bin/chromedriver.exe',
        $projectRoot.'/vendor/bin/chromedriver',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $resolved = realpath($candidate);

            return false === $resolved ? $candidate : $resolved;
        }
    }

    return null;
}

function addressingCommandString(array $parts): string
{
    return implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $parts));
}

function addressingRunPassthru(array $parts): int
{
    $exitCode = 0;
    passthru(addressingCommandString($parts), $exitCode);

    return $exitCode;
}

$projectRoot = addressingProjectRoot();
$driverBinary = addressingFindChromeDriverBinary($projectRoot);

if (null === $driverBinary) {
    fwrite(STDERR, "chromedriver binary is not available. Run \"php vendor/bin/bdi detect bin --no-interaction\" first.\n");
    exit(1);
}

$phpBinary = addressingPhp84Binary();
putenv('PANTHER_CHROME_DRIVER_BINARY='.$driverBinary);
$_SERVER['PANTHER_CHROME_DRIVER_BINARY'] = $driverBinary;

$exitCode = addressingRunPassthru([
    $phpBinary,
    $projectRoot.'/vendor/bin/phpunit',
    '-c',
    $projectRoot.'/phpunit.xml.dist',
    '--testsuite',
    'e2e',
]);

exit($exitCode);
