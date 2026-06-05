<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$applicationServicePaths = [
    'src/Service/Application/AddressWriteService.php',
    'src/Service/Application/AddressReadService.php',
    'src/Service/Application/AddressEvidenceService.php',
    'src/Service/Application/AddressOperationalService.php',
    'src/Service/Application/AddressQueueSummaryService.php',
    'src/Service/Application/AddressGovernanceSummaryService.php',
    'src/Service/Application/AddressPortfolioSummaryService.php',
];
$httpServicePaths = [
    'src/Service/Http/Address/AddressManageHttpService.php',
    'src/Service/Http/Address/AddressWriteHttpService.php',
    'src/Service/Http/Address/AddressReadHttpService.php',
    'src/Service/Http/Address/AddressSummaryHttpService.php',
    'src/Service/Http/Address/AddressOperationalHttpService.php',
    'src/Service/Http/Address/AddressHttpScopeService.php',
    'src/Service/Http/Address/AddressHttpResponderService.php',
];

$services = [];
foreach (array_merge($applicationServicePaths, $httpServicePaths) as $path) {
    $services[$path] = is_file($root.'/'.$path);
}

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'surface' => 'application_http',
    'status' => in_array(false, $services, true) ? 'incomplete' : 'ready',
    'services' => $services,
    'removedLegacyApplicationFacade' => !is_file($root.'/src/Service/Application/'.'Address'.'Service.php'),
    'removedHttpGodService' => !is_file($root.'/src/Service/Http/Address/'.'Address'.'Http'.'Service.php'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
