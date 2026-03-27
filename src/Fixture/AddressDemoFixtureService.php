<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Fixture;

use App\Entity\Record\AddressData;
use App\Repository\Persistence\AddressRepository;
use App\Service\Application\AddressService;
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

        $service = new AddressService(new AddressRepository($this->pdo));

        for ($index = 1; $index <= $count; ++$index) {
            $now = (new \DateTimeImmutable('-'.$this->randomInt(0, 120).' days'))->format('Y-m-d H:i:sP');

            $service->create(new AddressData(
                sprintf('demo-%04d', $index),
                'owner-'.$this->randomInt(1, 4),
                'vendor-'.$this->randomInt(1, 3),
                $this->streetAddress($index),
                $this->secondaryAddress(),
                $this->city(),
                $this->stateAbbr(),
                $this->postcode(),
                'US',
                null,
                null,
                null,
                null,
                $this->latitude(),
                $this->longitude(),
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

    private function randomInt(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    private function streetAddress(int $index): string
    {
        $streets = ['Main St', 'Oak Ave', 'Pine Rd', 'Cedar Blvd', 'Maple Dr'];

        return sprintf('%d %s', 100 + $index, $streets[$index % count($streets)]);
    }

    private function secondaryAddress(): ?string
    {
        return 0 === $this->randomInt(0, 2) ? null : 'Suite '.$this->randomInt(100, 999);
    }

    private function city(): string
    {
        $cities = ['Austin', 'Houston', 'Dallas', 'San Antonio', 'El Paso'];

        return $cities[$this->randomInt(0, count($cities) - 1)];
    }

    private function stateAbbr(): ?string
    {
        $states = [null, 'TX', 'CA', 'WA', 'NY'];

        return $states[$this->randomInt(0, count($states) - 1)];
    }

    private function postcode(): string
    {
        return str_pad((string) $this->randomInt(10000, 99999), 5, '0', STR_PAD_LEFT);
    }

    private function latitude(): float
    {
        return $this->randomInt(25000000, 49000000) / 1000000;
    }

    private function longitude(): float
    {
        return -($this->randomInt(67000000, 124000000) / 1000000);
    }
}
