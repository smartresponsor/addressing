<?php

declare(strict_types=1);

namespace App\Addressing\Tests\Unit\Factory;

use App\Addressing\Factory\AddressQueryFilterFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class AddressQueryFilterFactoryTest extends TestCase
{
    #[DataProvider('pageLimitProvider')]
    public function testPageLimitClampsRuntimeRange(?string $limit, int $expected): void
    {
        $query = null === $limit ? [] : ['limit' => $limit];
        $request = Request::create('/', 'GET', $query);

        self::assertSame($expected, (new AddressQueryFilterFactory())->pageLimit($request));
    }

    /** @return iterable<string, array{0: string|null, 1: int}> */
    public static function pageLimitProvider(): iterable
    {
        yield 'default' => [null, 25];
        yield 'lower bound' => ['0', 1];
        yield 'upper bound' => ['999', 200];
        yield 'inside range' => ['50', 50];
        yield 'non numeric' => ['invalid', 1];
    }

    public function testTenantCountryAndStringQueriesUseCanonicalShapes(): void
    {
        $request = Request::create('/', 'GET', [
            'ownerId' => 'owner-1',
            'vendorId' => 'vendor-1',
            'countryCode' => 'us',
            'q' => 'Houston',
        ]);
        $factory = new AddressQueryFilterFactory();

        self::assertSame(['owner-1', 'vendor-1'], $factory->tenantFromQuery($request));
        self::assertSame('US', $factory->queryCountryCodeOrNull($request));
        self::assertSame('Houston', $factory->queryStringOrNull($request, 'q'));
        self::assertNull($factory->queryStringOrNull($request, 'missing'));
        self::assertNull($factory->queryStringOrNull(Request::create('/', 'GET', ['q' => '']), 'q'));
    }

    public function testQueryStringRejectsNonScalarInput(): void
    {
        $request = Request::create('/', 'GET', ['q' => ['not', 'a', 'string']]);

        $this->expectException(\Symfony\Component\HttpFoundation\Exception\BadRequestException::class);

        (new AddressQueryFilterFactory())->queryStringOrNull($request, 'q');
    }

    public function testOperationalFiltersNormalizeTokensAndOptionalCriteria(): void
    {
        $request = Request::create('/', 'GET', [
            'sourceType' => ' VALIDATOR ',
            'governanceStatus' => ' DUPLICATE ',
            'revalidationPolicy' => ' Monthly ',
            'hasEvidence' => ' YES ',
            'revalidationDueBefore' => '2026-11-01T00:00:00+00:00',
            'queue' => 'revalidate',
            'expectedNormalizationVersion' => 'v3',
        ]);

        self::assertSame([
            'sourceType' => 'validator',
            'governanceStatus' => 'duplicate',
            'revalidationPolicy' => 'monthly',
            'hasEvidence' => true,
            'revalidationDueBefore' => '2026-11-01T00:00:00+00:00',
            'queue' => 'revalidate',
            'expectedNormalizationVersion' => 'v3',
        ], (new AddressQueryFilterFactory())->operationalFilters($request, true, true));
    }

    #[DataProvider('evidenceFlagProvider')]
    public function testOperationalFiltersNormalizeEvidenceFlag(mixed $value, ?bool $expected): void
    {
        $request = Request::create('/', 'GET', ['hasEvidence' => $value]);

        self::assertSame(
            $expected,
            (new AddressQueryFilterFactory())->operationalFilters($request)['hasEvidence'],
        );
    }

    /** @return iterable<string, array{0: mixed, 1: bool|null}> */
    public static function evidenceFlagProvider(): iterable
    {
        yield 'one' => ['1', true];
        yield 'true' => ['true', true];
        yield 'yes' => ['yes', true];
        yield 'zero' => ['0', false];
        yield 'false' => ['false', false];
        yield 'no' => ['no', false];
        yield 'unknown' => ['sometimes', null];
        yield 'non string' => [1, null];
    }

    public function testPortfolioFiltersAddSourceValidationAndNormalizationCriteria(): void
    {
        $request = Request::create('/', 'GET', [
            'sourceType' => 'unsupported',
            'governanceStatus' => 'unsupported',
            'revalidationPolicy' => 'unsupported',
            'sourceSystem' => 'crm',
            'validationProvider' => 'provider-a',
            'validationStatus' => ' VALIDATED ',
            'expectedNormalizationVersion' => 'v4',
        ]);

        self::assertSame([
            'sourceType' => null,
            'governanceStatus' => 'canonical',
            'revalidationPolicy' => null,
            'hasEvidence' => null,
            'revalidationDueBefore' => null,
            'expectedNormalizationVersion' => 'v4',
            'sourceSystem' => 'crm',
            'validationProvider' => 'provider-a',
            'validationStatus' => 'validated',
        ], (new AddressQueryFilterFactory())->portfolioFilters($request, true, true, true));
    }
}
