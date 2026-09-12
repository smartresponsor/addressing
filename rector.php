<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . '/src']);
    $rectorConfig->parallel();
    $rectorConfig->skip([
        RenameParamToMatchTypeRector::class,
        __DIR__ . '/src/Entity/AddressEvidenceSnapshotEntity.php',
        __DIR__ . '/src/Doctrine/AddressEntityMapper.php',
        __DIR__ . '/src/Repository/AbstractDoctrineAddressRepository.php',
        __DIR__ . '/src/Service/Application/AddressValidatedApplierService.php',
        __DIR__ . '/src/Service/Http/Address/AddressRateLimiterService.php',
    ]);
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_84,
        SetList::TYPE_DECLARATION,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::NAMING,
    ]);
};
