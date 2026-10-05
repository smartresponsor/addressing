<?php

declare(strict_types=1);

namespace Tests\Unit\Policy;

use App\Addressing\DTO\AddressManageDTO;
use App\Addressing\Factory\AddressInputFactory;
use App\Addressing\Policy\AddressGovernancePolicy;
use App\Addressing\Policy\AddressRecordPolicy;
use App\Addressing\Value\AddressCountryCode;
use App\Addressing\Value\AddressPostalCode;
use App\Addressing\Value\AddressStreetLine;
use App\Addressing\Value\AddressSubdivision;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AddressPolicyCoverageTest extends TestCase
{
    #[DataProvider('recordPolicyProvider')]
    public function testRecordPolicyNormalizersCoverCanonicalAndFallbackBranches(
        string $method,
        ?string $input,
        mixed $expected,
    ): void {
        self::assertSame($expected, AddressRecordPolicy::{$method}($input));
    }

    /** @return iterable<string, array{0: string, 1: string|null, 2: mixed}> */
    public static function recordPolicyProvider(): iterable
    {
        yield 'validation canonical' => ['normalizeValidationStatus', ' VALIDATED ', 'validated'];
        yield 'validation fallback' => ['normalizeValidationStatus', 'unsupported', 'unknown'];
        yield 'validation null' => ['normalizeValidationStatus', null, 'unknown'];
        yield 'source canonical' => ['normalizeSourceType', ' MANUAL ', 'manual'];
        yield 'source invalid' => ['normalizeSourceType', 'unsupported', null];
        yield 'source null' => ['normalizeSourceType', null, null];
        yield 'governance canonical' => ['normalizeGovernanceStatus', ' DUPLICATE ', 'duplicate'];
        yield 'governance fallback' => ['normalizeGovernanceStatus', 'unsupported', 'canonical'];
        yield 'governance null' => ['normalizeGovernanceStatus', null, 'canonical'];
        yield 'revalidation canonical' => ['normalizeRevalidationPolicy', ' MONTHLY ', 'monthly'];
        yield 'revalidation invalid' => ['normalizeRevalidationPolicy', 'unsupported', null];
        yield 'revalidation null' => ['normalizeRevalidationPolicy', null, null];
        yield 'last validation canonical' => ['normalizeLastValidationStatus', ' VALIDATED ', 'validated'];
        yield 'last validation invalid' => ['normalizeLastValidationStatus', 'pending', null];
        yield 'last validation null' => ['normalizeLastValidationStatus', null, null];
    }

    public function testValidationStatusSupportsExplicitFallback(): void
    {
        self::assertSame('pending', AddressRecordPolicy::normalizeValidationStatus('invalid', 'pending'));
    }

    public function testGovernancePolicyCoversNoopCanonicalAndLinkedTransitions(): void
    {
        self::assertSame([], AddressGovernancePolicy::normalizePatch('canonical', 'address-1', []));

        self::assertSame([
            'governance_status' => 'canonical',
            'duplicate_of_id' => null,
            'superseded_by_id' => null,
            'alias_of_id' => null,
            'conflict_with_id' => null,
        ], AddressGovernancePolicy::normalizePatch('conflict', 'address-1', [
            'governanceStatus' => 'canonical',
        ]));

        foreach ([
            'duplicate' => ['duplicateOfId', 'duplicate_of_id'],
            'superseded' => ['supersededById', 'superseded_by_id'],
            'alias' => ['aliasOfId', 'alias_of_id'],
            'conflict' => ['conflictWithId', 'conflict_with_id'],
        ] as $status => [$inputKey, $column]) {
            $normalized = AddressGovernancePolicy::normalizePatch('canonical', 'address-1', [
                'governanceStatus' => $status,
                $inputKey => ' address-2 ',
            ]);

            self::assertSame($status, $normalized['governance_status']);
            self::assertSame('address-2', $normalized[$column]);
        }
    }

    public function testGovernancePolicyRejectsInvalidTransitionMissingLinkAndSelfLink(): void
    {
        foreach ([
            ['duplicate', 'canonical', ['governanceStatus' => 'canonical']],
            ['canonical', 'address-1', ['governanceStatus' => 'duplicate']],
            ['canonical', 'address-1', ['governanceStatus' => 'duplicate', 'duplicateOfId' => 'address-1']],
        ] as [$currentStatus, $currentId, $patch]) {
            try {
                AddressGovernancePolicy::normalizePatch($currentStatus, $currentId, $patch);
                self::fail('Expected governance normalization to reject invalid input.');
            } catch (\RuntimeException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testAddressRecordCompositeStatesExposeCurrentRecordState(): void
    {
        $dto = new AddressManageDTO();
        $dto->line1 = '123 Main St';
        $dto->city = 'Houston';
        $dto->countryCode = 'US';

        $record = (new AddressInputFactory())->fromManageDto($dto, [
            'id' => 'address-1',
            'validationStatus' => 'validated',
            'validationProvider' => 'provider-a',
            'validatedAt' => '2026-10-05T12:00:00+00:00',
            'governanceStatus' => 'duplicate',
            'duplicateOfId' => 'address-2',
            'revalidationDueAt' => '2026-11-05T12:00:00+00:00',
            'revalidationPolicy' => 'monthly',
            'lastValidationProvider' => 'provider-b',
            'lastValidationStatus' => 'validated',
            'lastValidationScore' => 91,
        ]);

        self::assertSame('validated', $record->validationState()->validationStatus());
        self::assertSame('provider-a', $record->validationState()->validationProvider());
        self::assertSame('duplicate', $record->governanceState()->governanceStatus());
        self::assertSame('address-2', $record->governanceState()->duplicateOfId());
        self::assertSame('monthly', $record->revalidationState()->revalidationPolicy());
        self::assertSame(91, $record->revalidationState()->lastValidationScore());
    }

    public function testValueObjectConstructorsCoverValidAndInvalidBranches(): void
    {
        self::assertSame('US', (string) new AddressCountryCode(' us '));
        $this->assertInvalidValue(static fn (): AddressCountryCode => new AddressCountryCode('USA'));

        self::assertSame('77002', (string) new AddressPostalCode(' 77002 '));
        $this->assertInvalidValue(static fn (): AddressPostalCode => new AddressPostalCode(''));
        $this->assertInvalidValue(static fn (): AddressPostalCode => new AddressPostalCode('12'));
        $this->assertInvalidValue(static fn (): AddressPostalCode => new AddressPostalCode(str_repeat('1', 33)));

        self::assertSame('Main St', (string) new AddressStreetLine(' Main St '));
        $this->assertInvalidValue(static fn (): AddressStreetLine => new AddressStreetLine(''));
        $this->assertInvalidValue(static fn (): AddressStreetLine => new AddressStreetLine('x'));
        $this->assertInvalidValue(static fn (): AddressStreetLine => new AddressStreetLine(str_repeat('x', 257)));

        self::assertSame('TX', (string) new AddressSubdivision(' tx '));
        $this->assertInvalidValue(static fn (): AddressSubdivision => new AddressSubdivision(''));
        $this->assertInvalidValue(static fn (): AddressSubdivision => new AddressSubdivision(str_repeat('x', 33)));
    }

    /** @param callable(): object $factory */
    private function assertInvalidValue(callable $factory): void
    {
        try {
            $factory();
            self::fail('Expected invalid value object input to be rejected.');
        } catch (\InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
    }
}
