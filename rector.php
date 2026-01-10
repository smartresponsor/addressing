<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\{TypeDeclarationSetList,
    CodeQualitySetList,
    DeadCodeSetList,
    EarlyReturnSetList,
    NamingSetList
};

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([__DIR__ . '/src']);
    $rectorConfig->parallel();
    $rectorConfig->sets([
        TypeDeclarationSetList::LEVEL_UP,
        CodeQualitySetList::UP_TO_PHP_82,
        DeadCodeSetList::DEAD_CODE,
        EarlyReturnSetList::EARLY_RETURN,
        NamingSetList::NAMING,
    ]);
};
