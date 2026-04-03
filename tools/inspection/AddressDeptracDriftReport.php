<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$deptracPath = $root.'/config/addressing_deptrac.yaml';
$deptracContent = is_file($deptracPath) ? (string) file_get_contents($deptracPath) : '';

$signals = [
    'legacyControllerRegexPresent' => str_contains($deptracContent, '^App\\Controller\\.*'),
    'legacyHttpRegexPresent' => str_contains($deptracContent, '^App\\(Request|Response|Dto|Command)\\.*'),
    'legacyConfigRelativeSrcPresent' => str_contains($deptracContent, '- ./src'),
    'legacyCollectorTypePresent' => str_contains($deptracContent, 'type: className'),
    'currentCollectorTypePresent' => str_contains($deptracContent, 'type: classNameRegex'),
    'currentConfigRelativeSrcPresent' => str_contains($deptracContent, '- ../src'),
    'currentHttpControllerPathExists' => is_dir($root.'/src/Http/Controller'),
    'currentHttpPathExists' => is_dir($root.'/src/Http'),
    'currentRepositoryPathExists' => is_dir($root.'/src/Repository'),
    'currentServicePathExists' => is_dir($root.'/src/Service'),
    'currentContractPathExists' => is_dir($root.'/src/Contract'),
    'currentIntegrationPathExists' => is_dir($root.'/src/Integration'),
];

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => 'report',
    'deptracFilePresent' => is_file($deptracPath),
    'signals' => $signals,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
