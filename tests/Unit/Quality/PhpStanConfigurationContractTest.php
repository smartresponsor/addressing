<?php

declare(strict_types=1);

namespace Tests\Unit\Quality;

use PHPUnit\Framework\TestCase;

final class PhpStanConfigurationContractTest extends TestCase
{
    private const REMOVED_OPTIONS = [
        'checkMissingIterableValueType',
        'checkGenericClassInNonGenericObjectType',
    ];

    public function testDefaultConfigurationRejectsRemovedOptions(): void
    {
        $root = dirname(__DIR__, 3);
        $defaultConfig = file_get_contents($root.'/phpstan.neon');

        self::assertIsString($defaultConfig);

        foreach (self::REMOVED_OPTIONS as $removedOption) {
            self::assertStringNotContainsString($removedOption, $defaultConfig);
        }
    }

    public function testCanonicalConfigurationAndComposerScriptStayCompatible(): void
    {
        $root = dirname(__DIR__, 3);
        $canonicalConfig = file_get_contents($root.'/phpstan.neon.dist');
        $composerJson = file_get_contents($root.'/composer.json');

        self::assertIsString($canonicalConfig);
        self::assertIsString($composerJson);

        foreach (self::REMOVED_OPTIONS as $removedOption) {
            self::assertStringNotContainsString($removedOption, $canonicalConfig);
        }

        $composer = json_decode($composerJson, true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($composer);
        self::assertArrayHasKey('scripts', $composer);
        self::assertIsArray($composer['scripts']);
        self::assertArrayHasKey('phpstan', $composer['scripts']);
        self::assertIsString($composer['scripts']['phpstan']);
        self::assertStringContainsString('phpstan.neon.dist', $composer['scripts']['phpstan']);
    }
}
