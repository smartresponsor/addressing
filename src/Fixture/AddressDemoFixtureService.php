<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Fixture;

use App\Entity\Record\AddressData;
use App\Repository\Persistence\AddressRepository;
use App\Service\Application\AddressService;
use Faker\Factory;
use PDO;

final class AddressDemoFixtureService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function resetAndLoad(int $count = 50): int
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM address_outbox');
            $this->pdo->exec('DELETE FROM address_entity');
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        $faker = Factory::create('en_US');
        $service = new AddressService(new AddressRepository($this->pdo));

        for ($index = 1; $index <= $count; ++$index) {
            $now = (new \DateTimeImmutable('-'.$faker->numberBetween(0, 120).' days'))->format('Y-m-d H:i:sP');

            $service->create(new AddressData(
                sprintf('demo-%04d', $index),
                'owner-'.$faker->numberBetween(1, 4),
                'vendor-'.$faker->numberBetween(1, 3),
                $faker->streetAddress(),
                $faker->optional()->secondaryAddress(),
                $faker->city(),
                $faker->optional()->stateAbbr(),
                $faker->postcode(),
                'US',
                null,
                null,
                null,
                null,
                $faker->latitude(),
                $faker->longitude(),
                null,
                'pending',
                null,
                null,
                null,
                $now,
                null,
                null,
            ));
        }

        return $count;
    }
}
