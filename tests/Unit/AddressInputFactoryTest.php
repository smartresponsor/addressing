<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests;

use App\Addressing\DTO\AddressManageDTO;
use App\Addressing\Factory\AddressInputFactory;
use PHPUnit\Framework\TestCase;

final class AddressInputFactoryTest extends TestCase
{
    public function testFromManageDtoPreservesNormalizedRecordContract(): void
    {
        $dto = new AddressManageDTO();
        $dto->line1 = '123 Main St.';
        $dto->line2 = '  Suite 200  ';
        $dto->city = '  Houston  ';
        $dto->region = 'tx';
        $dto->postalCode = '770 02';
        $dto->countryCode = 'us';
        $dto->ownerId = '  owner-1  ';
        $dto->vendorId = '  vendor-1  ';

        $record = (new AddressInputFactory())->fromManageDto($dto, [
            'id' => 'address-1',
            'createdAt' => '2026-10-04 07:30:00+00:00',
            'latitude' => '29.7604',
            'longitude' => -95.3698,
            'sourceSystem' => 'test-suite',
            'normalizationVersion' => 'test-v1',
        ]);

        self::assertSame('address-1', $record->id());
        self::assertSame('owner-1', $record->ownerId());
        self::assertSame('vendor-1', $record->vendorId());
        self::assertSame('Suite 200', $record->line2());
        self::assertSame('Houston', $record->city());
        self::assertSame('TX', $record->region());
        self::assertSame('US', $record->countryCode());
        self::assertSame('123 main st.', $record->line1Norm());
        self::assertSame('houston', $record->cityNorm());
        self::assertSame('tx', $record->regionNorm());
        self::assertSame('77002', $record->postalCodeNorm());
        self::assertSame(29.7604, $record->latitude());
        self::assertSame(-95.3698, $record->longitude());
        self::assertSame('123 main st.|houston|tx|77002|us|owner-1|vendor-1', $record->dedupeKey());
        self::assertSame('2026-10-04 07:30:00+00:00', $record->createdAt());
        self::assertSame('test-suite', $record->sourceSystem());
        self::assertSame('test-v1', $record->normalizationVersion());
    }
}
