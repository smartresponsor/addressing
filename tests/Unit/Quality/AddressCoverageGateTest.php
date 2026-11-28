<?php

declare(strict_types=1);

namespace App\Addressing\Tests\Unit\Quality;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

final class AddressCoverageGateTest extends TestCase
{
    #[DataProvider('coverageCases')]
    public function testCiCoverageGuard(?string $metrics, string $minimum, int $expectedExit): void
    {
        $root = dirname(__DIR__, 3);
        $github = Yaml::parseFile($root.'/.github/workflows/addressing-gate-master.yml');
        $gitlab = Yaml::parseFile($root.'/.gitlab-ci.yml');
        Yaml::parseFile($root.'/.github/workflows/addressing-gate.yml');

        $githubRun = '';
        foreach ($github['jobs']['gate']['steps'] as $step) {
            if ('PHPUnit with coverage threshold' === ($step['name'] ?? null)) {
                $githubRun = $step['run'];
            }
        }

        $gitlabRun = implode("\n", $gitlab['phpunit']['script']);
        self::assertStringContainsString('--log-junit build/logs/junit.xml', $githubRun);
        self::assertStringContainsString('--log-junit build/logs/junit.xml', $gitlabRun);
        self::assertSame('build/logs/junit.xml', $gitlab['phpunit']['artifacts']['reports']['junit']);
        $githubGuard = $this->extractCoverageGuard($githubRun);
        self::assertSame($githubGuard, $this->extractCoverageGuard($gitlabRun));

        $temporaryRoot = sys_get_temp_dir().'/addressing-coverage-'.bin2hex(random_bytes(12));
        $filesystem = new Filesystem();
        $filesystem->mkdir($temporaryRoot.'/build/logs');

        try {
            if (null !== $metrics) {
                $filesystem->dumpFile(
                    $temporaryRoot.'/build/logs/clover.xml',
                    '<coverage><project>'.$metrics.'</project></coverage>',
                );
            }

            $process = new Process([PHP_BINARY, '-r', $githubGuard], $temporaryRoot, ['MIN_COVERAGE' => $minimum]);
            $process->run();

            self::assertSame($expectedExit, $process->getExitCode(), $process->getErrorOutput());
        } finally {
            $filesystem->remove($temporaryRoot);
        }
    }

    public function testHostedBrowserEvidenceIsGeneratedBeforeCanonEvaluation(): void
    {
        $root = dirname(__DIR__, 3);
        $github = Yaml::parseFile($root.'/.github/workflows/addressing-gate-master.yml');
        $gitlab = Yaml::parseFile($root.'/.gitlab-ci.yml');
        $githubRun = implode("\n", array_map(static fn (array $step): string => $step['run'] ?? '', $github['jobs']['gate']['steps']));
        $gitlabRun = implode("\n", $gitlab['phpunit']['script']);
        foreach ([$githubRun, $gitlabRun] as $script) {
            $browser = strpos($script, 'composer test:e2e:playwright');
            $report = strpos($script, 'composer report:behavioral-ui-coverage');
            $gate = strpos($script, 'composer gating');
            self::assertNotFalse($browser);
            self::assertNotFalse($report);
            self::assertNotFalse($gate);
            self::assertLessThan($report, $browser);
            self::assertLessThan($gate, $report);
            self::assertStringContainsString('php tools/e2e/ensure-schema.php', $script);
            self::assertStringContainsString('export ADDRESS_DB_PATH="$APP_VAR_DIR/addressing.sqlite"', $script);
        }
    }

    public function testBehavioralReportRequiresScopedBrowserReceipt(): void
    {
        $root = dirname(__DIR__, 3);
        $temporaryRoot = sys_get_temp_dir().'/addressing-browser-evidence-'.bin2hex(random_bytes(12));
        $filesystem = new Filesystem();
        try {
            $script = $temporaryRoot.'/tools/coverage/AddressBehavioralUiCoverageReport.php';
            $filesystem->copy($root.'/tools/coverage/AddressBehavioralUiCoverageReport.php', $script);
            $marker = $temporaryRoot.'/var/coverage/address-manage-playwright.json';
            $receipt = [
                'schema' => 'address-manage-playwright-v1',
                'passedAt' => '2026-01-01T00:00:00+00:00',
                'covered' => ['GET /address/manage', 'POST /address/manage'],
            ];
            foreach ([
                [$receipt, []],
                [array_replace($receipt, ['schema' => 'address-manage-playwright-v2']), []],
                [array_replace($receipt, [
                    'schema' => 'address-manage-playwright-v2',
                    'assertions' => ['manage.page.render', 'manage.address.create.owner-scope'],
                ]), ['manage.address.create.owner-scope']],
            ] as [$fixture, $expected]) {
                $filesystem->dumpFile($marker, json_encode($fixture, JSON_THROW_ON_ERROR));
                $process = new Process([PHP_BINARY, $script], $temporaryRoot);
                $process->mustRun();
                $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
                self::assertSame($expected, $report['dimensions']['critical']['covered']);
            }
        } finally {
            $filesystem->remove($temporaryRoot);
        }
    }

    /** @return iterable<string, array{?string, string, int}> */
    public static function coverageCases(): iterable
    {
        yield 'threshold' => ['<metrics statements="100" coveredstatements="80"/>', '80', 0];
        yield 'below threshold' => ['<metrics statements="100" coveredstatements="79"/>', '80', 1];
        yield 'missing file' => [null, '80', 1];
        yield 'missing metrics' => ['', '80', 1];
        yield 'zero denominator' => ['<metrics statements="0" coveredstatements="0"/>', '80', 1];
        yield 'malformed count' => ['<metrics statements="100x" coveredstatements="80"/>', '80', 1];
        yield 'impossible count' => ['<metrics statements="100" coveredstatements="101"/>', '80', 1];
        yield 'invalid threshold' => ['<metrics statements="100" coveredstatements="100"/>', 'invalid', 1];
    }

    private function extractCoverageGuard(string $script): string
    {
        self::assertSame(1, preg_match("/^\\s*php -r '(.*)'$/m", $script, $matches));
        if (!isset($matches[1])) {
            self::fail('Coverage guard capture is missing.');
        }

        return $matches[1];
    }
}
