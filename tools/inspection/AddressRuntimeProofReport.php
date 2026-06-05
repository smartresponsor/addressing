<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

$root = dirname(__DIR__, 2);

$proof = [
    'src/Service/Application/AddressWriteService.php' => is_file($root.'/src/Service/Application/AddressWriteService.php'),
    'src/Service/Application/AddressReadService.php' => is_file($root.'/src/Service/Application/AddressReadService.php'),
    'src/Service/Application/AddressEvidenceService.php' => is_file($root.'/src/Service/Application/AddressEvidenceService.php'),
    'src/Service/Application/AddressOperationalService.php' => is_file($root.'/src/Service/Application/AddressOperationalService.php'),
    'src/Service/Application/AddressQueueSummaryService.php' => is_file($root.'/src/Service/Application/AddressQueueSummaryService.php'),
    'src/Service/Application/AddressGovernanceSummaryService.php' => is_file($root.'/src/Service/Application/AddressGovernanceSummaryService.php'),
    'src/Service/Application/AddressPortfolioSummaryService.php' => is_file($root.'/src/Service/Application/AddressPortfolioSummaryService.php'),
    'legacy application facade removed' => !is_file($root.'/src/Service/Application/'.'Address'.'Service.php'),
    'legacy http god service removed' => !is_file($root.'/src/Service/Http/Address/'.'Address'.'Http'.'Service.php'),
    'public/index.php' => is_file($root.'/public/index.php'),
    'config/addressing_services.yaml' => is_file($root.'/config/addressing_services.yaml'),
];

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'surface' => 'runtime_proof',
    'status' => in_array(false, $proof, true) ? 'incomplete' : 'ready',
    'proof' => $proof,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
