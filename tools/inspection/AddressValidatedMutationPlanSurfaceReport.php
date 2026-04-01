<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$planPath = $root.'/src/Integration/Persistence/AddressValidatedMutationPlan.php';
$builderPath = $root.'/src/Integration/Persistence/AddressValidatedMutationPlanBuilder.php';
$builderContent = is_file($builderPath) ? (string) file_get_contents($builderPath) : '';

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => 'report',
    'checks' => [
        'src/Integration/Persistence/AddressValidatedMutationPlan.php' => is_file($planPath),
        'src/Integration/Persistence/AddressValidatedMutationPlanBuilder.php' => is_file($builderPath),
    ],
    'signals' => [
        'mutationPlanBuilderInjectsPdo' => str_contains($builderContent, 'private \\PDO $pdo'),
        'mutationPlanBuilderInjectsPayloadFactory' => str_contains($builderContent, 'private AddressValidatedPayloadFactory $addressValidatedPayloadFactory'),
        'mutationPlanBuilderDefinesBuildMethod' => str_contains($builderContent, 'public function build('),
        'mutationPlanBuilderDefinesJsonAssignmentMethod' => str_contains($builderContent, 'private function jsonAssignment('),
        'mutationPlanBuilderDefinesEncodePayloadMethod' => str_contains($builderContent, 'private function encodePayload('),
        'mutationPlanBuilderDefinesIsPgsqlMethod' => str_contains($builderContent, 'private function isPgsql('),
        'mutationPlanBuilderBuildsRawSha256' => str_contains($builderContent, '$rawSha256 = hash('),
        'mutationPlanBuilderBuildsGovernanceAssignments' => str_contains($builderContent, "governance_status = :governance_status")
            && str_contains($builderContent, "duplicate_of_id = :duplicate_of_id")
            && str_contains($builderContent, "superseded_by_id = :superseded_by_id")
            && str_contains($builderContent, "alias_of_id = :alias_of_id")
            && str_contains($builderContent, "conflict_with_id = :conflict_with_id"),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
