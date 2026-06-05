<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Fixture;

use App\Doctrine\AddressDoctrineSchemaManager;
use App\Http\Dto\AddressInputFactory;
use App\Http\Dto\AddressManageDto;
use App\Service\Application\AddressWriteService;
use Faker\Factory;
use Faker\Generator;

final readonly class AddressDemoFixtureService
{
    private Generator $generator;

    public function __construct(
        private AddressDoctrineSchemaManager $addressDoctrineSchemaManager,
        private AddressWriteService $addressWriteService,
        private AddressInputFactory $addressInputFactory,
    ) {
        $this->generator = Factory::create('en_US');
    }

    public function resetAndLoad(int $count = 50): int
    {
        $this->addressDoctrineSchemaManager->resetSchema();

        for ($index = 1; $index <= $count; ++$index) {
            $dto = $this->buildDto();
            $governanceStatus = match (true) {
                0 === $index % 11 => 'conflict',
                0 === $index % 7 => 'duplicate',
                0 === $index % 5 => 'alias',
                default => 'canonical',
            };
            $validationStatus = 0 === $index % 4 ? 'validated' : 'pending';
            $lastValidationStatus = 'validated' === $validationStatus ? 'validated' : 'uncertain';

            $address = $this->addressInputFactory->fromManageDto($dto, [
                'id' => sprintf('demo-%04d', $index),
                'createdAt' => $this->generator->dateTimeBetween('-120 days', '-3 days')->format('Y-m-d H:i:sP'),
                'latitude' => $this->generator->latitude(25, 49),
                'longitude' => $this->generator->longitude(-124, -67),
                'validationStatus' => $validationStatus,
                'validationProvider' => 'validated' === $validationStatus ? 'faker-validator' : null,
                'validatedAt' => 'validated' === $validationStatus ? $this->generator->dateTimeBetween('-60 days')->format('Y-m-d H:i:sP') : null,
                'sourceSystem' => 'symfony-fixture',
                'sourceType' => 0 === $index % 3 ? 'import' : 'manual',
                'sourceReference' => 'fixture-run-'.$index,
                'normalizationVersion' => 0 === $index % 2 ? 'canon-v2' : 'canon-v1',
                'validationFingerprint' => hash('sha256', 'demo-'.$index),
                'validationRaw' => ['provider' => 'faker-validator', 'input' => $dto->line1],
                'validationVerdict' => ['quality' => $this->generator->numberBetween(70, 99)],
                'validationDeliverable' => true,
                'validationGranularity' => 'premise',
                'validationQuality' => $this->generator->numberBetween(70, 99),
                'providerDigest' => 'sha256:'.hash('sha256', 'provider-'.$index),
                'governanceStatus' => $governanceStatus,
                'duplicateOfId' => 'duplicate' === $governanceStatus ? 'demo-0001' : null,
                'aliasOfId' => 'alias' === $governanceStatus ? 'demo-0002' : null,
                'conflictWithId' => 'conflict' === $governanceStatus ? 'demo-0003' : null,
                'revalidationDueAt' => $this->generator->dateTimeBetween('-2 days', '+90 days')->format('Y-m-d H:i:sP'),
                'revalidationPolicy' => 0 === $index % 2 ? 'quarterly' : 'monthly',
                'lastValidationProvider' => 'faker-validator',
                'lastValidationStatus' => $lastValidationStatus,
                'lastValidationScore' => $this->generator->numberBetween(70, 99),
            ]);

            $this->addressWriteService->create($address);
        }

        return $count;
    }

    private function buildDto(): AddressManageDto
    {
        $addressManageDto = new AddressManageDto();
        $addressManageDto->line1 = $this->generator->streetAddress();
        $line2 = $this->generator->optional(0.35)->randomElement([
            'Suite '.$this->generator->buildingNumber(),
            'Apt '.$this->generator->buildingNumber(),
            'Unit '.$this->generator->buildingNumber(),
        ]);
        $addressManageDto->line2 = is_string($line2) ? $line2 : null;
        $addressManageDto->city = $this->generator->city();
        $region = $this->generator->randomElement(['CA', 'FL', 'IL', 'NY', 'TX', 'WA']);
        $addressManageDto->region = is_string($region) ? $region : null;
        $addressManageDto->postalCode = $this->generator->postcode();
        $addressManageDto->countryCode = 'US';
        $addressManageDto->ownerId = 'owner-'.$this->generator->numberBetween(1, 4);
        $addressManageDto->vendorId = 'vendor-'.$this->generator->numberBetween(1, 3);

        return $addressManageDto;
    }
}
