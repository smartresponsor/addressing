<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$testDatabasePath = $root.'/tests/Support/TestDatabase.php';
$testRuntimeEnvironmentPath = $root.'/tests/Support/TestRuntimeEnvironment.php';
$serviceTestPath = $root.'/tests/Service/AddressServiceTest.php';
$functionalTestPath = $root.'/tests/Functional/AddressControllerFunctionalTest.php';

$testDatabaseContent = is_file($testDatabasePath) ? (string) file_get_contents($testDatabasePath) : '';
$serviceTestContent = is_file($serviceTestPath) ? (string) file_get_contents($serviceTestPath) : '';
$functionalTestContent = is_file($functionalTestPath) ? (string) file_get_contents($functionalTestPath) : '';

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => 'report',
    'checks' => [
        'tests/Support/TestDatabase.php' => is_file($testDatabasePath),
        'tests/Support/TestRuntimeEnvironment.php' => is_file($testRuntimeEnvironmentPath),
        'tests/Service/AddressServiceTest.php' => is_file($serviceTestPath),
        'tests/Functional/AddressControllerFunctionalTest.php' => is_file($functionalTestPath),
    ],
    'signals' => [
        'testDatabaseDelegatesToSchemaManager' => str_contains($testDatabaseContent, 'AddressSchemaManager::resetSchema('),
        'testDatabaseProvidesFreshSqlitePath' => str_contains($testDatabaseContent, 'freshSqlitePath('),
        'serviceTestUsesSharedTestDatabase' => str_contains($serviceTestContent, 'TestDatabase::createPdo(') && str_contains($serviceTestContent, 'TestDatabase::resetAddressSchema('),
        'serviceTestEmbedsSchemaSql' => str_contains($serviceTestContent, 'private function schemaSql('),
        'functionalTestUsesSharedTestDatabase' => str_contains($functionalTestContent, 'TestDatabase::freshSqlitePath(') && str_contains($functionalTestContent, 'TestDatabase::resetAddressSchema('),
        'functionalTestUsesRuntimeEnvironmentHelper' => str_contains($functionalTestContent, 'TestRuntimeEnvironment::configureSqliteAddressRuntime('),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
