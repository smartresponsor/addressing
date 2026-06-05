<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Unit;

use App\EntityInterface\Record\AddressInterface;
use App\RepositoryInterface\Persistence\AddressReadRepositoryInterface;
use App\Service\Application\AddressReadService;
use App\Value\Persistence\AddressPageCriteria;
use PHPUnit\Framework\TestCase;

final class AddressReadServiceUnitTest extends TestCase
{
    public function testDedupeReturnsNullWhenKeyIsNull(): void
    {
        $readRepository = $this->createMock(AddressReadRepositoryInterface::class);
        $readRepository->expects(self::never())->method('findByDedupeKey');

        $service = new AddressReadService($readRepository);

        self::assertNull($service->dedupe(null));
    }

    public function testSearchDelegatesToReadRepository(): void
    {
        $readRepository = $this->createMock(AddressReadRepositoryInterface::class);
        $readRepository->expects(self::once())
            ->method('findPage')
            ->with(self::callback(static function (AddressPageCriteria $criteria): bool {
                self::assertSame('owner-1', $criteria->ownerId());
                self::assertSame('vendor-1', $criteria->vendorId());
                self::assertSame('US', $criteria->countryCode());
                self::assertSame('main', $criteria->query());
                self::assertSame(25, $criteria->limit());
                self::assertNull($criteria->cursor());
                self::assertSame([], $criteria->filters());

                return true;
            }))
            ->willReturn(['items' => [], 'nextCursor' => null]);

        $service = new AddressReadService($readRepository);

        self::assertSame(['items' => [], 'nextCursor' => null], $service->search('owner-1', 'vendor-1', 'US', 'main', 25, null));
    }

    public function testGetReturnsAddressFromReadRepository(): void
    {
        $address = $this->createMock(AddressInterface::class);

        $readRepository = $this->createMock(AddressReadRepositoryInterface::class);
        $readRepository->expects(self::once())
            ->method('get')
            ->with('addr-1', 'owner-1', null)
            ->willReturn($address);

        $service = new AddressReadService($readRepository);

        self::assertSame($address, $service->get('addr-1', 'owner-1', null));
    }
}
