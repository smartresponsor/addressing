<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$servicePath = $root.'/src/Service/Application/AddressValidatedApplierService.php';
$payloadFactoryPath = $root.'/src/Service/Application/AddressValidatedPayloadFactory.php';
$tenantScopeHelperPath = $root.'/src/Integration/Persistence/AddressTenantScopeSqlHelper.php';
$serviceContent = is_file($servicePath) ? (string) file_get_contents($servicePath) : '';

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => 'report',
    'checks' => [
        'src/Service/Application/AddressValidatedApplierService.php' => is_file($servicePath),
        'src/Service/Application/AddressValidatedPayloadFactory.php' => is_file($payloadFactoryPath),
        'src/Integration/Persistence/AddressTenantScopeSqlHelper.php' => is_file($tenantScopeHelperPath),
    ],
    'signals' => [
        'validatedApplierInjectsPdo' => str_contains($serviceContent, 'private \\PDO $pdo'),
        'validatedApplierInjectsTenantScopeSqlHelper' => str_contains($serviceContent, 'private AddressTenantScopeSqlHelper $addressTenantScopeSqlHelper'),
        'validatedApplierInjectsPayloadFactory' => str_contains($serviceContent, 'private AddressValidatedPayloadFactory $addressValidatedPayloadFactory'),
        'validatedApplierUsesTenantScopeWhereClause' => str_contains($serviceContent, '$this->addressTenantScopeSqlHelper->whereClause('),
        'validatedApplierUsesTenantScopeParams' => str_contains($serviceContent, '$this->addressTenantScopeSqlHelper->params('),
        'validatedApplierUsesPayloadFactoryNormalizedSnapshot' => str_contains($serviceContent, '$this->addressValidatedPayloadFactory->normalizedSnapshot('),
        'validatedApplierUsesPayloadFactoryProviderDigest' => str_contains($serviceContent, '$this->addressValidatedPayloadFactory->providerDigest('),
        'validatedApplierUsesPayloadFactoryOutboxPayload' => str_contains($serviceContent, '$this->addressValidatedPayloadFactory->outboxPayload('),
        'validatedApplierDefinesTenantWhereClauseMethod' => str_contains($serviceContent, 'private function tenantWhereClause('),
        'validatedApplierDefinesTenantParamsMethod' => str_contains($serviceContent, 'private function tenantParams('),
        'validatedApplierDefinesHasEvidenceMethod' => str_contains($serviceContent, 'private function hasEvidence('),
        'validatedApplierDefinesBuildNormalizedSnapshotMethod' => str_contains($serviceContent, 'private function buildNormalizedSnapshot('),
        'validatedApplierDefinesBuildProviderDigestMethod' => str_contains($serviceContent, 'private function buildProviderDigest('),
        'validatedApplierDefinesGovernanceLinkIdMethod' => str_contains($serviceContent, 'private function governanceLinkId('),
        'validatedApplierDefinesSanitizeGovernanceLinkMethod' => str_contains($serviceContent, 'private function sanitizeGovernanceLink('),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
