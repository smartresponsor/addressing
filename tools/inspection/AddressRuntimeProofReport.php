<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'composer.json' => is_file($root.'/composer.json'),
    'phpunit.xml.dist' => is_file($root.'/phpunit.xml.dist'),
    'config/addressing_deptrac.yaml' => is_file($root.'/config/addressing_deptrac.yaml'),
    'src/Integration/Persistence/AddressSchemaManager.php' => is_file($root.'/src/Integration/Persistence/AddressSchemaManager.php'),
    'src/Integration/Persistence/AddressTenantScopeSqlHelper.php' => is_file($root.'/src/Integration/Persistence/AddressTenantScopeSqlHelper.php'),
    'src/Service/Application/AddressValidatedPayloadFactory.php' => is_file($root.'/src/Service/Application/AddressValidatedPayloadFactory.php'),
    'src/Service/Application/AddressValidatedApplierService.php' => is_file($root.'/src/Service/Application/AddressValidatedApplierService.php'),
    'src/Http/Controller/AddressController.php' => is_file($root.'/src/Http/Controller/AddressController.php'),
    'src/Service/Application/AddressService.php' => is_file($root.'/src/Service/Application/AddressService.php'),
    'src/Http/Factory/AddressQueryFilterFactory.php' => is_file($root.'/src/Http/Factory/AddressQueryFilterFactory.php'),
    'src/Http/Factory/AddressViewArrayFactory.php' => is_file($root.'/src/Http/Factory/AddressViewArrayFactory.php'),
    'src/Http/Factory/AddressApiPayloadFactory.php' => is_file($root.'/src/Http/Factory/AddressApiPayloadFactory.php'),
    'tools/inspection/AddressApplicationSurfaceReport.php' => is_file($root.'/tools/inspection/AddressApplicationSurfaceReport.php'),
    'tools/inspection/AddressValidatedApplierSurfaceReport.php' => is_file($root.'/tools/inspection/AddressValidatedApplierSurfaceReport.php'),
    'tools/qa/AddressTrustSurfaceRunner.php' => is_file($root.'/tools/qa/AddressTrustSurfaceRunner.php'),
];

$controllerPath = $root.'/src/Http/Controller/AddressController.php';
$controllerContent = is_file($controllerPath) ? (string) file_get_contents($controllerPath) : '';
$validatedApplierPath = $root.'/src/Service/Application/AddressValidatedApplierService.php';
$validatedApplierContent = is_file($validatedApplierPath) ? (string) file_get_contents($validatedApplierPath) : '';

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => in_array(false, $checks, true) ? 'incomplete' : 'ready',
    'checks' => $checks,
    'signals' => [
        'controllerInjectsAddressApiPayloadFactory' => str_contains($controllerContent, 'private AddressApiPayloadFactory $addressApiPayloadFactory'),
        'controllerDefinesJsonMethod' => str_contains($controllerContent, 'private function json('),
        'controllerDefinesReqStrMethod' => str_contains($controllerContent, 'private function reqStr('),
        'controllerDefinesOptStrMethod' => str_contains($controllerContent, 'private function optStr('),
        'controllerDefinesReqStringListMethod' => str_contains($controllerContent, 'private function reqStringList('),
        'controllerDefinesOptArrayMethod' => str_contains($controllerContent, 'private function optArray('),
        'controllerDefinesOptIntMethod' => str_contains($controllerContent, 'private function optInt('),
        'controllerDefinesOptFloatMethod' => str_contains($controllerContent, 'private function optFloat('),
        'controllerDefinesOperationalPatchMethod' => str_contains($controllerContent, 'private function operationalPatch('),
        'validatedApplierInjectsTenantScopeSqlHelper' => str_contains($validatedApplierContent, 'private AddressTenantScopeSqlHelper $addressTenantScopeSqlHelper'),
        'validatedApplierInjectsPayloadFactory' => str_contains($validatedApplierContent, 'private AddressValidatedPayloadFactory $addressValidatedPayloadFactory'),
        'validatedApplierDefinesTenantWhereClauseMethod' => str_contains($validatedApplierContent, 'private function tenantWhereClause('),
        'validatedApplierDefinesTenantParamsMethod' => str_contains($validatedApplierContent, 'private function tenantParams('),
        'validatedApplierDefinesHasEvidenceMethod' => str_contains($validatedApplierContent, 'private function hasEvidence('),
        'validatedApplierDefinesBuildNormalizedSnapshotMethod' => str_contains($validatedApplierContent, 'private function buildNormalizedSnapshot('),
        'validatedApplierDefinesBuildProviderDigestMethod' => str_contains($validatedApplierContent, 'private function buildProviderDigest('),
        'validatedApplierDefinesGovernanceLinkIdMethod' => str_contains($validatedApplierContent, 'private function governanceLinkId('),
        'validatedApplierDefinesSanitizeGovernanceLinkMethod' => str_contains($validatedApplierContent, 'private function sanitizeGovernanceLink('),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
