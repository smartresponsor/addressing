<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Fixture;

use App\Http\Dto\AddressInputFactory;
use App\Http\Dto\AddressManageDto;
use App\Integration\Persistence\AddressSchemaManager;
use App\Service\Application\AddressService;
use Faker\Factory;
use Faker\Generator;

final class AddressDemoFixtureService
{
    private Generator $faker;

    public function __construct(
        private readonly \PDO $pdo,
        private readonly AddressService $service,
        private readonly AddressInputFactory $inputFactory,
    ) {
        $this->faker = Factory::create('en_US');
    }

    public function resetAndLoad(int $count = 50): int
    {
        AddressSchemaManager::resetSchema($this->pdo, dirname(__DIR__, 2));

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

            $address = $this->inputFactory->fromManageDto($dto, [
                'id' => sprintf('demo-%04d', $index),
                'createdAt' => $this->faker->dateTimeBetween('-120 days', '-3 days')->format('Y-m-d H:i:sP'),
                'latitude' => (float) $this->faker->latitude(25, 49),
                'longitude' => (float) $this->faker->longitude(-124, -67),
                'validationStatus' => $validationStatus,
                'validationProvider' => 'validated' === $validationStatus ? 'faker-validator' : null,
                'validatedAt' => 'validated' === $validationStatus ? $this->faker->dateTimeBetween('-60 days', 'now')->format('Y-m-d H:i:sP') : null,
                'sourceSystem' => 'symfony-fixture',
                'sourceType' => 0 === $index % 3 ? 'import' : 'manual',
                'sourceReference' => 'fixture-run-'.$index,
                'normalizationVersion' => 0 === $index % 2 ? 'canon-v2' : 'canon-v1',
                'validationFingerprint' => hash('sha256', 'demo-'.$index),
                'validationRaw' => ['provider' => 'faker-validator', 'input' => $dto->line1],
                'validationVerdict' => ['quality' => $this->faker->numberBetween(70, 99)],
                'validationDeliverable' => true,
                'validationGranularity' => 'premise',
                'validationQuality' => $this->faker->numberBetween(70, 99),
                'providerDigest' => 'sha256:'.hash('sha256', 'provider-'.$index),
                'governanceStatus' => $governanceStatus,
                'duplicateOfId' => 'duplicate' === $governanceStatus ? 'demo-0001' : null,
                'aliasOfId' => 'alias' === $governanceStatus ? 'demo-0002' : null,
                'conflictWithId' => 'conflict' === $governanceStatus ? 'demo-0003' : null,
                'revalidationDueAt' => $this->faker->dateTimeBetween('-2 days', '+90 days')->format('Y-m-d H:i:sP'),
                'revalidationPolicy' => 0 === $index % 2 ? 'quarterly' : 'monthly',
                'lastValidationProvider' => 'faker-validator',
                'lastValidationStatus' => $lastValidationStatus,
                'lastValidationScore' => $this->faker->numberBetween(70, 99),
            ]);

            $this->service->create($address);
        }

        return $count;
    }

    private function buildDto(): AddressManageDto
    {
        $dto = new AddressManageDto();
        $dto->line1 = $this->faker->streetAddress();
        $dto->line2 = $this->faker->optional(0.35)->secondaryAddress();
        $dto->city = $this->faker->city();
        $dto->region = $this->faker->stateAbbr();
        $dto->postalCode = $this->faker->postcode();
        $dto->countryCode = 'US';
        $dto->ownerId = 'owner-'.$this->faker->numberBetween(1, 4);
        $dto->vendorId = 'vendor-'.$this->faker->numberBetween(1, 3);

        return $dto;
    }
}
