<?php

declare(strict_types=1);

namespace App\Addressing\Tests\Unit\Builder\Persistence;

use App\Addressing\Builder\Persistence\AddressTenantScopeSqlBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AddressTenantScopeSqlBuilderTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, ?string, string, array<string, string>}>
     */
    public static function scopeProvider(): iterable
    {
        yield 'owner and vendor' => [
            'owner-1',
            'vendor-1',
            '(owner_id = :owner_id AND vendor_id = :vendor_id)',
            [':owner_id' => 'owner-1', ':vendor_id' => 'vendor-1'],
        ];
        yield 'owner only' => [
            'owner-1',
            null,
            '(owner_id = :owner_id)',
            [':owner_id' => 'owner-1'],
        ];
        yield 'vendor only' => [
            null,
            'vendor-1',
            '(vendor_id = :vendor_id)',
            [':vendor_id' => 'vendor-1'],
        ];
        yield 'unscoped' => [
            null,
            null,
            '1 = 1',
            [],
        ];
    }

    /**
     * @param array<string, string> $expectedParams
     */
    #[DataProvider('scopeProvider')]
    public function testBuildsMatchingPredicateAndParameters(
        ?string $ownerId,
        ?string $vendorId,
        string $expectedClause,
        array $expectedParams,
    ): void {
        $builder = new AddressTenantScopeSqlBuilder();

        self::assertSame($expectedClause, $builder->whereClause($ownerId, $vendorId));
        self::assertSame($expectedParams, $builder->params($ownerId, $vendorId));
    }
}
