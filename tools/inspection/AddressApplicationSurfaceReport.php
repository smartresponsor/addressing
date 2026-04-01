<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root.'/src/Http/Controller/AddressController.php';
$servicePath = $root.'/src/Service/Application/AddressService.php';
$queryFactoryPath = $root.'/src/Http/Factory/AddressQueryFilterFactory.php';
$viewFactoryPath = $root.'/src/Http/Factory/AddressViewArrayFactory.php';
$payloadFactoryPath = $root.'/src/Http/Factory/AddressApiPayloadFactory.php';
$controllerContent = is_file($controllerPath) ? (string) file_get_contents($controllerPath) : '';
$serviceContent = is_file($servicePath) ? (string) file_get_contents($servicePath) : '';

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => 'report',
    'checks' => [
        'src/Http/Controller/AddressController.php' => is_file($controllerPath),
        'src/Service/Application/AddressService.php' => is_file($servicePath),
        'src/Http/Factory/AddressQueryFilterFactory.php' => is_file($queryFactoryPath),
        'src/Http/Factory/AddressViewArrayFactory.php' => is_file($viewFactoryPath),
        'src/Http/Factory/AddressApiPayloadFactory.php' => is_file($payloadFactoryPath),
    ],
    'signals' => [
        'controllerInjectsRepositoryDirectly' => str_contains($controllerContent, 'private AddressRepository $addressRepository'),
        'controllerInjectsAddressService' => str_contains($controllerContent, 'private AddressService $addressService'),
        'controllerInjectsAddressQueryFilterFactory' => str_contains($controllerContent, 'private AddressQueryFilterFactory $addressQueryFilterFactory'),
        'controllerInjectsAddressViewArrayFactory' => str_contains($controllerContent, 'private AddressViewArrayFactory $addressViewArrayFactory'),
        'controllerInjectsAddressApiPayloadFactory' => str_contains($controllerContent, 'private AddressApiPayloadFactory $addressApiPayloadFactory'),
        'controllerUsesServiceCreate' => str_contains($controllerContent, '$this->addressService->create('),
        'controllerUsesServiceGet' => str_contains($controllerContent, '$this->addressService->get('),
        'controllerUsesServiceSearch' => str_contains($controllerContent, '$this->addressService->search('),
        'controllerUsesServiceMarkDeleted' => str_contains($controllerContent, '$this->addressService->markDeleted('),
        'controllerUsesQueryFilterFactoryOperationalFilters' => str_contains($controllerContent, '$this->addressQueryFilterFactory->operationalFilters('),
        'controllerUsesQueryFilterFactoryPortfolioFilters' => str_contains($controllerContent, '$this->addressQueryFilterFactory->portfolioFilters('),
        'controllerUsesQueryFilterFactoryTenant' => str_contains($controllerContent, '$this->addressQueryFilterFactory->tenantFromQuery('),
        'controllerUsesViewArrayFactoryToArray' => str_contains($controllerContent, '$this->addressViewArrayFactory->toArray('),
        'controllerUsesViewArrayFactoryPreviewRow' => str_contains($controllerContent, '$this->addressViewArrayFactory->previewRow('),
        'controllerUsesPayloadFactoryDecodeJson' => str_contains($controllerContent, '$this->addressApiPayloadFactory->decodeJsonRequest('),
        'controllerUsesPayloadFactoryCreateAddressData' => str_contains($controllerContent, '$this->addressApiPayloadFactory->createAddressData('),
        'controllerUsesPayloadFactoryCreateAddressValidated' => str_contains($controllerContent, '$this->addressApiPayloadFactory->createAddressValidated('),
        'controllerUsesPayloadFactoryOperationalPatch' => str_contains($controllerContent, '$this->addressApiPayloadFactory->operationalPatch('),
        'controllerUsesPayloadFactoryRequireStringList' => str_contains($controllerContent, '$this->addressApiPayloadFactory->requireStringList('),
        'controllerDefinesJsonMethod' => str_contains($controllerContent, 'private function json('),
        'controllerDefinesReqStrMethod' => str_contains($controllerContent, 'private function reqStr('),
        'controllerDefinesOptStrMethod' => str_contains($controllerContent, 'private function optStr('),
        'controllerDefinesReqStringListMethod' => str_contains($controllerContent, 'private function reqStringList('),
        'controllerDefinesOptArrayMethod' => str_contains($controllerContent, 'private function optArray('),
        'controllerDefinesOptIntMethod' => str_contains($controllerContent, 'private function optInt('),
        'controllerDefinesOptFloatMethod' => str_contains($controllerContent, 'private function optFloat('),
        'controllerDefinesOperationalPatchMethod' => str_contains($controllerContent, 'private function operationalPatch('),
        'serviceProvidesMarkDeleted' => str_contains($serviceContent, 'public function markDeleted('),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
