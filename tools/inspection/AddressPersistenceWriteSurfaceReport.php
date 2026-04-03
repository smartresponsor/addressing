<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'composer.json' => is_file($root.'/composer.json'),
    'phpunit.xml.dist' => is_file($root.'/phpunit.xml.dist'),
    'config/addressing_deptrac.yaml' => is_file($root.'/config/addressing_deptrac.yaml'),
    'src/Integration/Persistence/AddressSchemaManager.php' => is_file($root.'/src/Integration/Persistence/AddressSchemaManager.php'),
    'src/Integration/Persistence/AddressTenantScopeSqlHelper.php' => is_file($root.'/src/Integration/Persistence/AddressTenantScopeSqlHelper.php'),
    'src/Integration/Persistence/AddressValidatedMutationPlan.php' => is_file($root.'/src/Integration/Persistence/AddressValidatedMutationPlan.php'),
    'src/Integration/Persistence/AddressValidatedMutationPlanBuilder.php' => is_file($root.'/src/Integration/Persistence/AddressValidatedMutationPlanBuilder.php'),
    'src/Integration/Persistence/AddressEvidenceSnapshotWriter.php' => is_file($root.'/src/Integration/Persistence/AddressEvidenceSnapshotWriter.php'),
    'src/Integration/Persistence/AddressOutboxWriter.php' => is_file($root.'/src/Integration/Persistence/AddressOutboxWriter.php'),
    'src/Service/Application/AddressValidatedPayloadFactory.php' => is_file($root.'/src/Service/Application/AddressValidatedPayloadFactory.php'),
    'src/Service/Application/AddressValidatedApplierService.php' => is_file($root.'/src/Service/Application/AddressValidatedApplierService.php'),
    'tools/inspection/AddressPersistenceWriteSurfaceReport.php' => is_file($root.'/tools/inspection/AddressPersistenceWriteSurfaceReport.php'),
    'tools/qa/AddressTrustSurfaceRunner.php' => is_file($root.'/tools/qa/AddressTrustSurfaceRunner.php'),
];

$validatedApplierPath = $root.'/src/Service/Application/AddressValidatedApplierService.php';
$content = '';
if (is_file($validatedApplierPath)) {
    $validatedApplierContent = file_get_contents($validatedApplierPath);
    if (false !== $validatedApplierContent) {
        $content = $validatedApplierContent;
    }
}

$payload = json_encode([
    'component' => 'Addressing',
    'status' => in_array(false, $checks, true) ? 'incomplete' : 'ready',
    'checks' => $checks,
    'signals' => [
        'usesEvidenceWriter' => str_contains($content, 'AddressEvidenceSnapshotWriter'),
        'usesOutboxWriter' => str_contains($content, 'AddressOutboxWriter'),
        'noInlineSnapshotInsert' => !str_contains($content, 'address_evidence_snapshot'),
        'noInlineOutboxInsert' => !str_contains($content, 'address_outbox'),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (false === $payload) {
    throw new RuntimeException('report_payload_encode_failed');
}

fwrite(STDOUT, $payload);
