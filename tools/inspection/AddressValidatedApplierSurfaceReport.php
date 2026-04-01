<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$servicePath = $root.'/src/Service/Application/AddressValidatedApplierService.php';
$payloadFactoryPath = $root.'/src/Service/Application/AddressValidatedPayloadFactory.php';
$tenantScopeHelperPath = $root.'/src/Integration/Persistence/AddressTenantScopeSqlHelper.php';
$mutationPlanPath = $root.'/src/Integration/Persistence/AddressValidatedMutationPlan.php';
$mutationPlanBuilderPath = $root.'/src/Integration/Persistence/AddressValidatedMutationPlanBuilder.php';
$evidenceWriterPath = $root.'/src/Integration/Persistence/AddressEvidenceSnapshotWriter.php';
$outboxWriterPath = $root.'/src/Integration/Persistence/AddressOutboxWriter.php';
$serviceContent = is_file($servicePath) ? (string) file_get_contents($servicePath) : '';

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => 'report',
    'checks' => [
        'src/Service/Application/AddressValidatedApplierService.php' => is_file($servicePath),
        'src/Service/Application/AddressValidatedPayloadFactory.php' => is_file($payloadFactoryPath),
        'src/Integration/Persistence/AddressTenantScopeSqlHelper.php' => is_file($tenantScopeHelperPath),
        'src/Integration/Persistence/AddressValidatedMutationPlan.php' => is_file($mutationPlanPath),
        'src/Integration/Persistence/AddressValidatedMutationPlanBuilder.php' => is_file($mutationPlanBuilderPath),
        'src/Integration/Persistence/AddressEvidenceSnapshotWriter.php' => is_file($evidenceWriterPath),
        'src/Integration/Persistence/AddressOutboxWriter.php' => is_file($outboxWriterPath),
    ],
    'signals' => [
        'validatedApplierInjectsPdo' => str_contains($serviceContent, 'private \\PDO $pdo'),
        'validatedApplierInjectsTenantScopeSqlHelper' => str_contains($serviceContent, 'private AddressTenantScopeSqlHelper $addressTenantScopeSqlHelper'),
        'validatedApplierInjectsPayloadFactory' => str_contains($serviceContent, 'private AddressValidatedPayloadFactory $addressValidatedPayloadFactory'),
        'validatedApplierInjectsMutationPlanBuilder' => str_contains($serviceContent, 'private AddressValidatedMutationPlanBuilder $addressValidatedMutationPlanBuilder'),
        'validatedApplierInjectsEvidenceWriter' => str_contains($serviceContent, 'private AddressEvidenceSnapshotWriter $addressEvidenceSnapshotWriter'),
        'validatedApplierInjectsOutboxWriter' => str_contains($serviceContent, 'private AddressOutboxWriter $addressOutboxWriter'),
        'validatedApplierUsesTenantScopeWhereClause' => str_contains($serviceContent, '$this->addressTenantScopeSqlHelper->whereClause('),
        'validatedApplierUsesTenantScopeParams' => str_contains($serviceContent, '$this->addressTenantScopeSqlHelper->params('),
        'validatedApplierUsesPayloadFactoryOutboxPayload' => str_contains($serviceContent, '$this->addressValidatedPayloadFactory->outboxPayload('),
        'validatedApplierUsesMutationPlanBuilder' => str_contains($serviceContent, '$this->addressValidatedMutationPlanBuilder->build('),
        'validatedApplierUsesEvidenceWriter' => str_contains($serviceContent, '$this->addressEvidenceSnapshotWriter->write('),
        'validatedApplierUsesOutboxWriter' => str_contains($serviceContent, '$this->addressOutboxWriter->write('),
        'validatedApplierDefinesInlineFieldsArray' => str_contains($serviceContent, '$fields = []'),
        'validatedApplierDefinesAppendEvidenceSnapshotMethod' => str_contains($serviceContent, 'private function appendEvidenceSnapshot('),
        'validatedApplierDefinesAppendOutboxMethod' => str_contains($serviceContent, 'private function appendOutbox('),
        'validatedApplierDefinesJsonAssignmentMethod' => str_contains($serviceContent, 'private function jsonAssignment('),
        'validatedApplierDefinesBuildNormalizedSnapshotMethod' => str_contains($serviceContent, 'private function buildNormalizedSnapshot('),
        'validatedApplierDefinesBuildProviderDigestMethod' => str_contains($serviceContent, 'private function buildProviderDigest('),
        'validatedApplierDefinesGovernanceLinkIdMethod' => str_contains($serviceContent, 'private function governanceLinkId('),
        'validatedApplierDefinesSanitizeGovernanceLinkMethod' => str_contains($serviceContent, 'private function sanitizeGovernanceLink('),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
