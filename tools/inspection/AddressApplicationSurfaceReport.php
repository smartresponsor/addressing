<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root.'/src/Http/Controller/AddressController.php';
$servicePath = $root.'/src/Service/Application/AddressService.php';
$queryFactoryPath = $root.'/src/Http/Factory/AddressQueryFilterFactory.php';
$viewFactoryPath = $root.'/src/Http/Factory/AddressViewArrayFactory.php';
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
    ],
    'signals' => [
        'controllerInjectsRepositoryDirectly' => str_contains($controllerContent, 'private AddressRepository $addressRepository'),
        'controllerInjectsAddressService' => str_contains($controllerContent, 'private AddressService $addressService'),
        'controllerInjectsAddressQueryFilterFactory' => str_contains($controllerContent, 'private AddressQueryFilterFactory $addressQueryFilterFactory'),
        'controllerInjectsAddressViewArrayFactory' => str_contains($controllerContent, 'private AddressViewArrayFactory $addressViewArrayFactory'),
        'controllerUsesServiceCreate' => str_contains($controllerContent, '$this->addressService->create('),
        'controllerUsesServiceGet' => str_contains($controllerContent, '$this->addressService->get('),
        'controllerUsesServiceSearch' => str_contains($controllerContent, '$this->addressService->search('),
        'controllerUsesServiceMarkDeleted' => str_contains($controllerContent, '$this->addressService->markDeleted('),
        'controllerUsesServiceQueueSummary' => str_contains($controllerContent, '$this->addressService->operationalQueueSummary('),
        'controllerUsesServiceCountryPortfolio' => str_contains($controllerContent, '$this->addressService->countryPortfolioSummary('),
        'controllerUsesServiceSourcePortfolio' => str_contains($controllerContent, '$this->addressService->sourcePortfolioSummary('),
        'controllerUsesServiceValidationPortfolio' => str_contains($controllerContent, '$this->addressService->validationPortfolioSummary('),
        'controllerUsesServiceNormalizationPortfolio' => str_contains($controllerContent, '$this->addressService->normalizationPortfolioSummary('),
        'controllerUsesServiceGovernanceCluster' => str_contains($controllerContent, '$this->addressService->governanceClusterSummary('),
        'controllerUsesServicePatchOperational' => str_contains($controllerContent, '$this->addressService->patchOperational('),
        'controllerUsesQueryFilterFactoryOperationalFilters' => str_contains($controllerContent, '$this->addressQueryFilterFactory->operationalFilters('),
        'controllerUsesQueryFilterFactoryPortfolioFilters' => str_contains($controllerContent, '$this->addressQueryFilterFactory->portfolioFilters('),
        'controllerUsesQueryFilterFactoryTenant' => str_contains($controllerContent, '$this->addressQueryFilterFactory->tenantFromQuery('),
        'controllerUsesViewArrayFactoryToArray' => str_contains($controllerContent, '$this->addressViewArrayFactory->toArray('),
        'controllerUsesViewArrayFactoryPreviewRow' => str_contains($controllerContent, '$this->addressViewArrayFactory->previewRow('),
        'controllerDefinesOperationalFiltersMethod' => str_contains($controllerContent, 'private function operationalFilters('),
        'controllerDefinesPortfolioFiltersMethod' => str_contains($controllerContent, 'private function portfolioFilters('),
        'controllerDefinesToArrayMethod' => str_contains($controllerContent, 'private function toArray('),
        'serviceProvidesMarkDeleted' => str_contains($serviceContent, 'public function markDeleted('),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
