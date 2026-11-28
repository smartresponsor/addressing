<?php

declare(strict_types=1);

namespace App\Addressing\Tests\Unit\Quality;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

final class AddressLockedSiblingManifestTest extends TestCase
{
    public function testCurrentLockAndCiProvisioningContract(): void
    {
        $root = dirname(__DIR__, 3);
        $process = new Process([PHP_BINARY, $root.'/tools/ci/AddressLockedSiblingManifest.php']);
        $process->mustRun();
        $lines = explode("\n", trim($process->getOutput()));
        self::assertCount(8, $lines);
        $lock = json_decode((string) file_get_contents($root.'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
        $locked = [];
        foreach (array_merge($lock['packages'], $lock['packages-dev']) as $package) {
            if ('path' === ($package['dist']['type'] ?? null)) {
                $locked[$package['name']] = $package['dist'];
            }
        }
        foreach ($lines as $line) {
            $fields = explode("\t", $line);
            self::assertCount(4, $fields);
            [$name, $directory, $repository, $ref] = $fields;
            self::assertSame('../'.$directory, $locked[$name]['url']);
            self::assertSame($locked[$name]['reference'], $ref);
            self::assertMatchesRegularExpression('/^[a-f0-9]{40}$/D', $ref);
            if ('tabling/table' === $name) {
                self::assertSame('tabling-', $repository);
            }
        }

        foreach (['addressing-gate.yml', 'addressing-gate-master.yml'] as $file) {
            $workflow = Yaml::parseFile($root.'/.github/workflows/'.$file);
            $steps = $workflow['jobs']['gate']['steps'];
            $provisionIndex = null;
            $installIndex = null;
            foreach ($steps as $index => $step) {
                if ('bash tools/ci/address-provision-locked-siblings.sh' === ($step['run'] ?? null)) {
                    $provisionIndex = $index;
                }
                if (str_starts_with($step['run'] ?? '', 'composer install ')) {
                    $installIndex = $index;
                }
                if (str_starts_with($step['uses'] ?? '', 'actions/cache@')) {
                    self::assertStringNotContainsString('vendor', $step['with']['path']);
                }
            }
            self::assertNotNull($provisionIndex);
            self::assertNotNull($installIndex);
            self::assertLessThan($installIndex, $provisionIndex);
        }

        $gitlab = Yaml::parseFile($root.'/.gitlab-ci.yml');
        $before = $gitlab['.php_job_template']['before_script'];
        self::assertContains('bash tools/ci/address-provision-locked-siblings.sh', $before);
        self::assertLessThan(
            array_search('composer install --no-progress --no-interaction --prefer-dist', $before, true),
            array_search('bash tools/ci/address-provision-locked-siblings.sh', $before, true),
        );
    }

    public function testProvisioningPreflightRefusesMissingCredentialAndExistingSibling(): void
    {
        $bash = (new \Symfony\Component\Process\ExecutableFinder())->find('bash');
        if (null === $bash) {
            self::markTestSkipped('Bash is required for the hosted CI preflight.');
        }

        $root = dirname(__DIR__, 3);
        $temporaryRoot = sys_get_temp_dir().'/addressing-sibling-preflight-'.bin2hex(random_bytes(12));
        $checkout = $temporaryRoot.'/Addressing';
        $filesystem = new Filesystem();
        try {
            foreach (['tools/ci/AddressLockedSiblingManifest.php', 'tools/ci/address-provision-locked-siblings.sh', 'composer.lock'] as $path) {
                $filesystem->copy($root.'/'.$path, $checkout.'/'.$path);
            }

            $script = $checkout.'/tools/ci/address-provision-locked-siblings.sh';
            (new Process([$bash, '-n', $script]))->mustRun();

            $process = new Process([$bash, $script], $checkout, ['CI' => 'true', 'ADDRESSING_GATING_READ_TOKEN' => '']);
            $process->run();
            self::assertSame(1, $process->getExitCode());
            self::assertStringContainsString('Missing repository-scoped Gating read credential', $process->getErrorOutput());

            $filesystem->dumpFile($temporaryRoot.'/Gating/untouched', 'preserve');
            $process = new Process([$bash, $script], $checkout, ['CI' => 'true', 'ADDRESSING_GATING_READ_TOKEN' => 'synthetic-test-value']);
            $process->run();
            self::assertSame(1, $process->getExitCode());
            self::assertStringContainsString('Existing sibling path refused: Gating', $process->getErrorOutput());
            self::assertSame('preserve', file_get_contents($temporaryRoot.'/Gating/untouched'));
            self::assertDirectoryDoesNotExist($temporaryRoot.'/Collectioning');
            self::assertStringNotContainsString('synthetic-test-value', $process->getOutput().$process->getErrorOutput());
        } finally {
            $filesystem->remove($temporaryRoot);
        }
    }

    #[DataProvider('invalidLockCases')]
    public function testInvalidLockFailsBeforeProvisioning(string $case): void
    {
        $root = dirname(__DIR__, 3);
        $lock = json_decode((string) file_get_contents($root.'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($lock['packages'] as $index => $package) {
            if ('collectioning/collection' !== $package['name']) {
                continue;
            }
            match ($case) {
                'unexpected name' => $lock['packages'][$index]['name'] = 'unknown/package',
                'wrong path' => $lock['packages'][$index]['dist']['url'] = '../Other',
                'wrong ref' => $lock['packages'][$index]['dist']['reference'] = 'master',
                'wrong type' => $lock['packages'][$index]['dist']['type'] = 'zip',
                'duplicate' => $lock['packages'][] = $package,
                'missing' => $lock['packages'] = array_values(array_diff_key($lock['packages'], [$index => true])),
                default => throw new \LogicException('unknown_test_case'),
            };
            break;
        }

        $temporaryRoot = sys_get_temp_dir().'/addressing-sibling-lock-'.bin2hex(random_bytes(12));
        $filesystem = new Filesystem();
        try {
            $filesystem->copy($root.'/tools/ci/AddressLockedSiblingManifest.php', $temporaryRoot.'/tools/ci/AddressLockedSiblingManifest.php');
            $filesystem->dumpFile($temporaryRoot.'/composer.lock', json_encode($lock, JSON_THROW_ON_ERROR));
            $process = new Process([PHP_BINARY, $temporaryRoot.'/tools/ci/AddressLockedSiblingManifest.php']);
            $process->run();
            self::assertNotSame(0, $process->getExitCode());
            self::assertSame('', $process->getOutput());
        } finally {
            $filesystem->remove($temporaryRoot);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidLockCases(): iterable
    {
        foreach (['unexpected name', 'wrong path', 'wrong ref', 'wrong type', 'duplicate', 'missing'] as $case) {
            yield $case => [$case];
        }
    }
}
