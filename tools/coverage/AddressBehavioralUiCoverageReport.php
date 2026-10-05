<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$functionalMarkerPath = $root.'/var/coverage/address-http-functional.json';
$markerPath = $root.'/var/coverage/address-manage-playwright.json';
$marker = is_file($markerPath)
    ? json_decode((string) file_get_contents($markerPath), true)
    : null;

$manageCovered = is_array($marker)
    && 'address-manage-playwright-v1' === ($marker['schema'] ?? null)
    && is_string($marker['passedAt'] ?? null);

$functionalEligible = [
    'GET /address/manage',
    'POST /address/manage',
    'POST /api/address',
    'GET /api/address/page',
    'GET /api/address/search',
    'GET /api/address/queue-summary',
    'GET /api/address/country-portfolio',
    'GET /api/address/source-portfolio',
    'GET /api/address/validation-portfolio',
    'GET /api/address/normalization-portfolio',
    'POST /api/address/operational-batch',
    'GET /api/address/{id}',
    'DELETE /api/address/{id}',
    'PATCH /api/address/{id}',
    'POST /api/address/{id}/validated',
    'GET /api/address/{id}/governance-cluster',
];

$functionalMarker = is_file($functionalMarkerPath)
    ? json_decode((string) file_get_contents($functionalMarkerPath), true)
    : null;
$functionalCovered = is_array($functionalMarker)
    && 'address-http-functional-v1' === ($functionalMarker['schema'] ?? null)
    && is_string($functionalMarker['passedAt'] ?? null)
    && is_array($functionalMarker['covered'] ?? null)
        ? array_values(array_intersect($functionalEligible, $functionalMarker['covered']))
        : [];

if ($manageCovered) {
    $functionalCovered = array_values(array_unique([...$functionalCovered, 'GET /address/manage', 'POST /address/manage']));
}

$manageBehavior = [
    'manage.page.render',
    'manage.address.create.owner-scope',
];

$evidence = [
    'schema' => 'behavioral-ui-coverage-v2',
    'producer' => [
        'kind' => 'repository_script',
        'script' => 'report:behavioral-ui-coverage',
    ],
    'generatedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
    'dimensions' => [
        'functional' => [
            'eligible' => $functionalEligible,
            'covered' => $functionalCovered,
        ],
        'behavioral' => [
            'eligible' => $manageBehavior,
            'covered' => $manageCovered ? $manageBehavior : [],
        ],
        'ui' => [
            'eligible' => $manageBehavior,
            'covered' => $manageCovered ? $manageBehavior : [],
        ],
        'critical' => [
            'eligible' => ['manage.address.create.owner-scope'],
            'covered' => $manageCovered ? ['manage.address.create.owner-scope'] : [],
        ],
    ],
];

$coverageDir = $root.'/var/coverage';
if (!is_dir($coverageDir) && !mkdir($coverageDir, 0777, true) && !is_dir($coverageDir)) {
    throw new RuntimeException('behavioral_ui_coverage_directory_create_failed');
}

$outputPath = $coverageDir.'/behavioral-ui.json';
$encoded = json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($encoded) || false === file_put_contents($outputPath, $encoded.PHP_EOL)) {
    throw new RuntimeException('behavioral_ui_coverage_write_failed');
}

fwrite(STDOUT, $encoded.PHP_EOL);
