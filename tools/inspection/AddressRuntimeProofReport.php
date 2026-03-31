<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'composer.json' => is_file($root.'/composer.json'),
    'phpunit.xml.dist' => is_file($root.'/phpunit.xml.dist'),
    'config/addressing_deptrac.yaml' => is_file($root.'/config/addressing_deptrac.yaml'),
    'src/Integration/Persistence/AddressSchemaManager.php' => is_file($root.'/src/Integration/Persistence/AddressSchemaManager.php'),
    'tools/support/AddressRuntimeBootstrap.php' => is_file($root.'/tools/support/AddressRuntimeBootstrap.php'),
    'tests/object-manager.php' => is_file($root.'/tests/object-manager.php'),
    'tests/console-application.php' => is_file($root.'/tests/console-application.php'),
    'tests/Support/TestDatabase.php' => is_file($root.'/tests/Support/TestDatabase.php'),
    'tests/Support/TestRuntimeEnvironment.php' => is_file($root.'/tests/Support/TestRuntimeEnvironment.php'),
    'tests/Service/AddressServiceTest.php' => is_file($root.'/tests/Service/AddressServiceTest.php'),
    'tests/Security/SymfonySecurityTest.php' => is_file($root.'/tests/Security/SymfonySecurityTest.php'),
    'src/Http/Controller/AddressController.php' => is_file($root.'/src/Http/Controller/AddressController.php'),
    'src/Service/Application/AddressService.php' => is_file($root.'/src/Service/Application/AddressService.php'),
    'src/Http/Factory/AddressQueryFilterFactory.php' => is_file($root.'/src/Http/Factory/AddressQueryFilterFactory.php'),
    'src/Http/Factory/AddressViewArrayFactory.php' => is_file($root.'/src/Http/Factory/AddressViewArrayFactory.php'),
    'src/Http/Factory/AddressApiPayloadFactory.php' => is_file($root.'/src/Http/Factory/AddressApiPayloadFactory.php'),
    'tools/inspection/AddressApplicationSurfaceReport.php' => is_file($root.'/tools/inspection/AddressApplicationSurfaceReport.php'),
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
    'tools/inspection/AddressPackageSurfaceReport.php' => is_file($root.'/tools/inspection/AddressPackageSurfaceReport.php'),
];

$controllerInjectsRepositoryDirectly = false;
$controllerInjectsAddressQueryFilterFactory = false;
$controllerInjectsAddressViewArrayFactory = false;
$controllerInjectsAddressApiPayloadFactory = false;
$controllerDefinesJsonMethod = false;
$controllerDefinesReqStrMethod = false;
$controllerDefinesOptStrMethod = false;
$controllerDefinesReqStringListMethod = false;
$controllerDefinesOptArrayMethod = false;
$controllerDefinesOptIntMethod = false;
$controllerDefinesOptFloatMethod = false;
$controllerDefinesOperationalPatchMethod = false;

$controllerPath = $root.'/src/Http/Controller/AddressController.php';
if (is_file($controllerPath)) {
    $controllerContent = (string) file_get_contents($controllerPath);
    $controllerInjectsRepositoryDirectly = str_contains($controllerContent, 'private AddressRepository $addressRepository');
    $controllerInjectsAddressQueryFilterFactory = str_contains($controllerContent, 'private AddressQueryFilterFactory $addressQueryFilterFactory');
    $controllerInjectsAddressViewArrayFactory = str_contains($controllerContent, 'private AddressViewArrayFactory $addressViewArrayFactory');
    $controllerInjectsAddressApiPayloadFactory = str_contains($controllerContent, 'private AddressApiPayloadFactory $addressApiPayloadFactory');
    $controllerDefinesJsonMethod = str_contains($controllerContent, 'private function json(');
    $controllerDefinesReqStrMethod = str_contains($controllerContent, 'private function reqStr(');
    $controllerDefinesOptStrMethod = str_contains($controllerContent, 'private function optStr(');
    $controllerDefinesReqStringListMethod = str_contains($controllerContent, 'private function reqStringList(');
    $controllerDefinesOptArrayMethod = str_contains($controllerContent, 'private function optArray(');
    $controllerDefinesOptIntMethod = str_contains($controllerContent, 'private function optInt(');
    $controllerDefinesOptFloatMethod = str_contains($controllerContent, 'private function optFloat(');
    $controllerDefinesOperationalPatchMethod = str_contains($controllerContent, 'private function operationalPatch(');
}

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => in_array(false, $checks, true) ? 'incomplete' : 'ready',
    'checks' => $checks,
    'signals' => [
        'controllerInjectsRepositoryDirectly' => $controllerInjectsRepositoryDirectly,
        'controllerInjectsAddressQueryFilterFactory' => $controllerInjectsAddressQueryFilterFactory,
        'controllerInjectsAddressViewArrayFactory' => $controllerInjectsAddressViewArrayFactory,
        'controllerInjectsAddressApiPayloadFactory' => $controllerInjectsAddressApiPayloadFactory,
        'controllerDefinesJsonMethod' => $controllerDefinesJsonMethod,
        'controllerDefinesReqStrMethod' => $controllerDefinesReqStrMethod,
        'controllerDefinesOptStrMethod' => $controllerDefinesOptStrMethod,
        'controllerDefinesReqStringListMethod' => $controllerDefinesReqStringListMethod,
        'controllerDefinesOptArrayMethod' => $controllerDefinesOptArrayMethod,
        'controllerDefinesOptIntMethod' => $controllerDefinesOptIntMethod,
        'controllerDefinesOptFloatMethod' => $controllerDefinesOptFloatMethod,
        'controllerDefinesOperationalPatchMethod' => $controllerDefinesOperationalPatchMethod,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
