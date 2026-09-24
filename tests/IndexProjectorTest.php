<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests;

use App\Addressing\Entity\AddressIndexEntity;
use App\Addressing\Event\AddressCreatedEvent;
use App\Addressing\Normalizer\AddressIndexNormalizer;
use App\Addressing\Projection\AddressIndex\AddressIndexProjector;
use App\Addressing\Repository\AddressIndex\DoctrineAddressIndexRepository;
use App\Addressing\Service\Projection\AddressIndex\AddressIndexProjectorService;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class AddressIndexProjectorTest extends TestCase
{
    public function testProjectionIntoRepository(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([AddressIndexEntity::class]);
        $repo = new DoctrineAddressIndexRepository($entityManager);
        $projector = new AddressIndexProjector($repo, new AddressIndexNormalizer(), new AddressIndexProjectorService());

        $evt = new AddressCreatedEvent('123 Main St', null, 'Houston', 'TX', '77002', 'US');
        $projector->onAddressCreated($evt);

        $list = $repo->search('Hou', 'US', 10);
        self::assertGreaterThanOrEqual(1, count($list));
        self::assertSame('US', $list[0]->country);
    }
}
