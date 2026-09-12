<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

final class CanonicalClassPlacementTest extends TestCase
{
    public function testRoleSuffixesLiveInCanonicalDirectories(): void
    {
        $src = dirname(__DIR__, 3).'/src';
        $rules = [
            'Policy.php' => '/Policy/',
            'Event.php' => '/Event/',
            'Message.php' => '/Message/',
            'Factory.php' => '/Factory/',
            'Context.php' => '/Context/',
            'Config.php' => '/Config/',
            'Builder.php' => '/Builder/',
            'Plan.php' => '/Plan/',
            'RepositoryInterface.php' => '/RepositoryInterface/',
            'Repository.php' => '/Repository/',
            'Service.php' => '/Service/',
            'Middleware.php' => '/Middleware/',
        ];

        $violations = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src));

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || 'php' !== $file->getExtension()) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            foreach ($rules as $suffix => $directory) {
                if (str_ends_with($file->getFilename(), $suffix) && !str_contains($path, $directory)) {
                    $violations[] = sprintf('%s must live under %s', $path, $directory);
                }
            }
        }

        self::assertSame([], $violations, implode("\n", $violations));
    }

    public function testRemovedLegacyRoleDirectoriesStayAbsent(): void
    {
        $src = dirname(__DIR__, 3).'/src';
        $forbidden = [
            $src.'/Repository/Persistence',
            $src.'/RepositoryInterface/Persistence',
            $src.'/Integration/Persistence',
            $src.'/Lifecycle',
        ];

        foreach ($forbidden as $directory) {
            self::assertDirectoryDoesNotExist($directory, sprintf('Legacy role directory returned: %s', $directory));
        }
    }
}
