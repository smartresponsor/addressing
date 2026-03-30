<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'composer.json' => is_file($root.'/composer.json'),
    'phpunit.xml.dist' => is_file($root.'/phpunit.xml.dist'),
    'config/addressing_deptrac.yaml' => is_file($root.'/config/addressing_deptrac.yaml'),
    'tools/support/AddressRuntimeBootstrap.php' => is_file($root.'/tools/support/AddressRuntimeBootstrap.php'),
    'tests/object-manager.php' => is_file($root.'/tests/object-manager.php'),
    'tests/console-application.php' => is_file($root.'/tests/console-application.php'),
    'tests/Support/TestDatabase.php' => is_file($root.'/tests/Support/TestDatabase.php'),
    'tests/Support/TestRuntimeEnvironment.php' => is_file($root.'/tests/Support/TestRuntimeEnvironment.php'),
    'tests/Security/SymfonySecurityTest.php' => is_file($root.'/tests/Security/SymfonySecurityTest.php'),
    'bin/address-demo-reset' => is_file($root.'/bin/address-demo-reset'),
    'bin/console' => is_file($root.'/bin/console'),
    'tools/smoke/category-runtime-smoke.php' => is_file($root.'/tools/smoke/category-runtime-smoke.php'),
    'tools/smoke/category-fixture-sanity.php' => is_file($root.'/tools/smoke/category-fixture-sanity.php'),
    'tools/smoke/category-container-boot-smoke.php' => is_file($root.'/tools/smoke/category-container-boot-smoke.php'),
    'tools/smoke/category-fixture-load-smoke.php' => is_file($root.'/tools/smoke/category-fixture-load-smoke.php'),
    'tools/smoke/category-doctrine-mapping-smoke.php' => is_file($root.'/tools/smoke/category-doctrine-mapping-smoke.php'),
    'tools/smoke/category-graphql-smoke.php' => is_file($root.'/tools/smoke/category-graphql-smoke.php'),
    'tools/qa/AddressTrustSurfaceRunner.php' => is_file($root.'/tools/qa/AddressTrustSurfaceRunner.php'),
    'tools/inspection/AddressRuntimeSyncSummary.php' => is_file($root.'/tools/inspection/AddressRuntimeSyncSummary.php'),
    'tools/inspection/AddressTestSupportSurfaceReport.php' => is_file($root.'/tools/inspection/AddressTestSupportSurfaceReport.php'),
];

$objectManagerUsesRuntimeBootstrap = false;
$consoleUsesRuntimeBootstrap = false;
$demoResetUsesRuntimeBootstrap = false;
$serviceTestUsesSharedTestDatabase = false;
$functionalTestUsesSharedTestDatabase = false;
$serviceTestEmbedsSchemaSql = false;
$securityTestUsesSharedTestDatabase = false;

$objectManagerPath = $root.'/tests/object-manager.php';
if (is_file($objectManagerPath)) {
    $objectManagerUsesRuntimeBootstrap = str_contains((string) file_get_contents($objectManagerPath), 'AddressRuntimeBootstrap');
}

$consolePath = $root.'/tests/console-application.php';
if (is_file($consolePath)) {
    $consoleUsesRuntimeBootstrap = str_contains((string) file_get_contents($consolePath), 'AddressRuntimeBootstrap');
}

$demoResetPath = $root.'/bin/address-demo-reset';
if (is_file($demoResetPath)) {
    $demoResetUsesRuntimeBootstrap = str_contains((string) file_get_contents($demoResetPath), 'AddressRuntimeBootstrap');
}

$serviceTestPath = $root.'/tests/Service/AddressServiceTest.php';
if (is_file($serviceTestPath)) {
    $serviceTestContent = (string) file_get_contents($serviceTestPath);
    $serviceTestUsesSharedTestDatabase = str_contains($serviceTestContent, 'TestDatabase::createPdo(') && str_contains($serviceTestContent, 'TestDatabase::resetAddressSchema(');
    $serviceTestEmbedsSchemaSql = str_contains($serviceTestContent, 'private function schemaSql(');
}

$functionalTestPath = $root.'/tests/Functional/AddressControllerFunctionalTest.php';
if (is_file($functionalTestPath)) {
    $functionalTestContent = (string) file_get_contents($functionalTestPath);
    $functionalTestUsesSharedTestDatabase = str_contains($functionalTestContent, 'TestDatabase::freshSqlitePath(') && str_contains($functionalTestContent, 'TestDatabase::resetAddressSchema(');
}

$securityTestPath = $root.'/tests/Security/SymfonySecurityTest.php';
if (is_file($securityTestPath)) {
    $securityTestContent = (string) file_get_contents($securityTestPath);
    $securityTestUsesSharedTestDatabase = str_contains($securityTestContent, 'TestDatabase::createInMemorySqlitePdo(');
}

$transitionLayer = [
    'bin/address-demo-reset-runtime' => is_file($root.'/bin/address-demo-reset-runtime'),
    'tests/object-manager.runtime.php' => is_file($root.'/tests/object-manager.runtime.php'),
    'tests/runtime-console-application.php' => is_file($root.'/tests/runtime-console-application.php'),
    'tools/inspection/AddressWave2SyncSummary.php' => is_file($root.'/tools/inspection/AddressWave2SyncSummary.php'),
];

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => in_array(false, $checks, true) ? 'incomplete' : 'ready',
    'checks' => $checks,
    'signals' => [
        'objectManagerUsesRuntimeBootstrap' => $objectManagerUsesRuntimeBootstrap,
        'consoleUsesRuntimeBootstrap' => $consoleUsesRuntimeBootstrap,
        'demoResetUsesRuntimeBootstrap' => $demoResetUsesRuntimeBootstrap,
        'serviceTestUsesSharedTestDatabase' => $serviceTestUsesSharedTestDatabase,
        'serviceTestEmbedsSchemaSql' => $serviceTestEmbedsSchemaSql,
        'functionalTestUsesSharedTestDatabase' => $functionalTestUsesSharedTestDatabase,
        'securityTestUsesSharedTestDatabase' => $securityTestUsesSharedTestDatabase,
        'transitionLayerRetired' => !in_array(true, $transitionLayer, true),
    ],
    'transitionLayer' => $transitionLayer,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
