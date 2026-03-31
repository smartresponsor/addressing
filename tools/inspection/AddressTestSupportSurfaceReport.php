<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$schemaManagerPath = $root.'/src/Integration/Persistence/AddressSchemaManager.php';
$testDatabasePath = $root.'/tests/Support/TestDatabase.php';
$testRuntimeEnvironmentPath = $root.'/tests/Support/TestRuntimeEnvironment.php';
$serviceTestPath = $root.'/tests/Service/AddressServiceTest.php';
$functionalTestPath = $root.'/tests/Functional/AddressControllerFunctionalTest.php';
$integrationFixtureTestPath = $root.'/tests/Integration/AddressDemoFixtureIntegrationTest.php';
$securityTestPath = $root.'/tests/Security/SymfonySecurityTest.php';

$schemaManagerContent = is_file($schemaManagerPath) ? (string) file_get_contents($schemaManagerPath) : '';
$testDatabaseContent = is_file($testDatabasePath) ? (string) file_get_contents($testDatabasePath) : '';
$serviceTestContent = is_file($serviceTestPath) ? (string) file_get_contents($serviceTestPath) : '';
$functionalTestContent = is_file($functionalTestPath) ? (string) file_get_contents($functionalTestPath) : '';
$integrationFixtureTestContent = is_file($integrationFixtureTestPath) ? (string) file_get_contents($integrationFixtureTestPath) : '';
$securityTestContent = is_file($securityTestPath) ? (string) file_get_contents($securityTestPath) : '';

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => 'report',
    'checks' => [
        'src/Integration/Persistence/AddressSchemaManager.php' => is_file($schemaManagerPath),
        'tests/Support/TestDatabase.php' => is_file($testDatabasePath),
        'tests/Support/TestRuntimeEnvironment.php' => is_file($testRuntimeEnvironmentPath),
        'tests/Service/AddressServiceTest.php' => is_file($serviceTestPath),
        'tests/Functional/AddressControllerFunctionalTest.php' => is_file($functionalTestPath),
        'tests/Integration/AddressDemoFixtureIntegrationTest.php' => is_file($integrationFixtureTestPath),
        'tests/Security/SymfonySecurityTest.php' => is_file($securityTestPath),
    ],
    'signals' => [
        'schemaManagerDefinesTenantScopeConstraint' => str_contains($schemaManagerContent, 'address_tenant_scope_chk'),
        'schemaManagerDefinesEvidenceScopeConstraint' => str_contains($schemaManagerContent, 'address_evidence_snapshot_scope_chk'),
        'schemaManagerDefinesOutboxStreamColumn' => str_contains($schemaManagerContent, 'stream TEXT NOT NULL DEFAULT ''address'''),
        'schemaManagerDefinesDedupeTriggers' => str_contains($schemaManagerContent, 'trg_address_dedupe_autofill') && str_contains($schemaManagerContent, 'trg_address_dedupe_autofill_update'),
        'testDatabaseDelegatesToSchemaManager' => str_contains($testDatabaseContent, 'AddressSchemaManager::resetSchema('),
        'testDatabaseProvidesFreshSqlitePath' => str_contains($testDatabaseContent, 'freshSqlitePath('),
        'testDatabaseProvidesInMemorySqlitePdo' => str_contains($testDatabaseContent, 'createInMemorySqlitePdo('),
        'serviceTestUsesSharedTestDatabase' => str_contains($serviceTestContent, 'TestDatabase::createInMemorySqlitePdo(') && str_contains($serviceTestContent, 'TestDatabase::resetAddressSchema('),
        'serviceTestEmbedsSchemaSql' => str_contains($serviceTestContent, 'private function schemaSql('),
        'functionalTestUsesSharedTestDatabase' => str_contains($functionalTestContent, 'TestDatabase::freshSqlitePath(') && str_contains($functionalTestContent, 'TestDatabase::resetAddressSchema('),
        'functionalTestUsesRuntimeEnvironmentHelper' => str_contains($functionalTestContent, 'TestRuntimeEnvironment::configureSqliteAddressRuntime('),
        'integrationFixtureTestUsesSharedTestDatabase' => str_contains($integrationFixtureTestContent, 'TestDatabase::createPdo(') && str_contains($integrationFixtureTestContent, 'TestDatabase::resetAddressSchema('),
        'securityTestUsesSharedTestDatabase' => str_contains($securityTestContent, 'TestDatabase::createInMemorySqlitePdo('),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
