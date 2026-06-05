<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests;

use App\Entity\AddressIndexEntity;
use App\Projection\AddressIndex\AddressIndexNormalizer;
use App\Projection\AddressIndex\AddressIndexProjector;
use App\Projection\AddressIndex\DoctrineAddressIndexRepository;
use App\Service\Application\Event\AddressCreatedEvent;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class AddressIndexProjectorTest extends TestCase
{
    public function testProjectionIntoRepository(): void
    {
        $entityManager = TestDatabase::createInMemoryEntityManager([AddressIndexEntity::class]);
        $repo = new DoctrineAddressIndexRepository($entityManager);
        $projector = new AddressIndexProjector($repo, new AddressIndexNormalizer());

        $evt = new AddressCreatedEvent('123 Main St', null, 'Houston', 'TX', '77002', 'US');
        $projector->onAddressCreated($evt);

        $list = $repo->search('Hou', 'US', 10);
        self::assertGreaterThanOrEqual(1, count($list));
        self::assertSame('US', $list[0]->country);
    }
}
