<?php

declare(strict_types=1);

namespace App\Addressing\Tests\Unit\Quality;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class AddressTrustSurfaceRunnerTest extends TestCase
{
    public function testMissingRouteAndReportEvidenceFailsClosed(): void
    {
        $root = dirname(__DIR__, 3);
        $temporaryRoot = sys_get_temp_dir().'/addressing-trust-'.bin2hex(random_bytes(12));
        $filesystem = new Filesystem();

        try {
            foreach ([
                'tools/qa/AddressTrustSurfaceRunner.php',
                'tools/inspection/AddressRouteInventoryReport.php',
                'docs/addressing-entity-boundary-contract.md',
            ] as $path) {
                $filesystem->copy($root.'/'.$path, $temporaryRoot.'/'.$path);
            }

            $process = new Process([PHP_BINARY, $temporaryRoot.'/tools/qa/AddressTrustSurfaceRunner.php']);
            $process->run();

            self::assertSame(1, $process->getExitCode());
            $report = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('partial', $report['status']);
            self::assertFalse($report['route_inventory_ready']);
        } finally {
            $filesystem->remove($temporaryRoot);
        }
    }
}
