<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace Tests\Unit\Coverage;

use App\Addressing\Contract\AddressInterface;
use App\Addressing\Contract\Message\AddressValidated;
use App\Addressing\Contract\Message\AddressValidationVerdict;
use App\Addressing\Policy\AddressGovernancePolicy;
use App\Addressing\Policy\AddressRecordPolicy;
use App\Addressing\RepositoryInterface\AddressRateLimitRepositoryInterface;
use App\Addressing\RepositoryInterface\AddressReadRepositoryInterface;
use App\Addressing\RepositoryInterface\AddressWriteRepositoryInterface;
use App\Addressing\Service\Application\AddressReadService;
use App\Addressing\Service\Application\AddressWriteService;
use App\Addressing\Service\Http\Address\AddressRateLimiterService;
use PHPUnit\Framework\TestCase;

final class AddressCoverageClosureTest extends TestCase
{
    public function testApplicationServicesDelegateAllPublicOperations(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $writeRepository = $this->createMock(AddressWriteRepositoryInterface::class);
        $writeRepository->expects(self::once())->method('create')->with($address);
        $writeRepository->expects(self::once())->method('update')->with($address);
        $writeRepository->expects(self::once())->method('delete')->with('address-1', 'owner-1', 'vendor-1');

        $writeService = new AddressWriteService($writeRepository);
        $writeService->create($address);
        $writeService->update($address);
        $writeService->markDeleted('address-1', 'owner-1', 'vendor-1');

        $readRepository = $this->createMock(AddressReadRepositoryInterface::class);
        $readRepository->expects(self::once())->method('get')->willReturn($address);
        $readRepository->expects(self::once())->method('findByDedupeKey')->with('dedupe-1')->willReturn($address);
        $readRepository->expects(self::once())->method('findPage')->willReturn(['items' => [$address], 'nextCursor' => null]);

        $readService = new AddressReadService($readRepository);
        self::assertSame($address, $readService->get('address-1', 'owner-1', 'vendor-1'));
        self::assertNull($readService->dedupe(null));
        self::assertSame($address, $readService->dedupe('dedupe-1'));
        self::assertSame(
            ['items' => [$address], 'nextCursor' => null],
            $readService->search('owner-1', 'vendor-1', 'US', 'main', 20, null, ['status' => 'validated']),
        );
    }

    public function testRateLimiterSupportsDisabledAndPersistedModes(): void
    {
        self::assertTrue((new AddressRateLimiterService(null))->check('client', 'search'));

        $repository = $this->createMock(AddressRateLimitRepositoryInterface::class);
        $repository->expects(self::exactly(2))
            ->method('checkAndIncrement')
            ->with('client', 'search', 90)
            ->willReturnOnConsecutiveCalls(true, false);

        $service = new AddressRateLimiterService($repository, 60, 30);
        self::assertTrue($service->check('client', 'search'));
        self::assertFalse($service->check('client', 'search'));
    }

    public function testRecordPolicyCoversCanonicalAndFallbackTokens(): void
    {
        self::assertSame('validated', AddressRecordPolicy::normalizeValidationStatus(' VALIDATED '));
        self::assertSame('fallback', AddressRecordPolicy::normalizeValidationStatus('bad', 'fallback'));
        self::assertSame('manual', AddressRecordPolicy::normalizeSourceType(' MANUAL '));
        self::assertNull(AddressRecordPolicy::normalizeSourceType('bad'));
        self::assertSame('duplicate', AddressRecordPolicy::normalizeGovernanceStatus(' DUPLICATE '));
        self::assertSame('canonical', AddressRecordPolicy::normalizeGovernanceStatus(null));
        self::assertSame('weekly', AddressRecordPolicy::normalizeRevalidationPolicy(' WEEKLY '));
        self::assertNull(AddressRecordPolicy::normalizeRevalidationPolicy('bad'));
        self::assertSame('validated', AddressRecordPolicy::normalizeLastValidationStatus(' VALIDATED '));
        self::assertNull(AddressRecordPolicy::normalizeLastValidationStatus('pending'));
    }

    public function testGovernancePolicyCoversLinksAndInvalidTransitions(): void
    {
        self::assertSame([], AddressGovernancePolicy::normalizePatch('canonical', 'address-1', []));
        self::assertSame(
            'canonical',
            AddressGovernancePolicy::normalizePatch('conflict', 'address-1', ['governanceStatus' => 'canonical'])['governance_status'],
        );

        $cases = [
            ['duplicate', 'duplicateOfId', 'duplicate_of_id', 'address-2'],
            ['superseded', 'supersededById', 'superseded_by_id', 'address-3'],
            ['alias', 'aliasOfId', 'alias_of_id', 'address-4'],
            ['conflict', 'conflictWithId', 'conflict_with_id', 'address-5'],
        ];
        foreach ($cases as [$status, $inputKey, $column, $linkId]) {
            $normalized = AddressGovernancePolicy::normalizePatch(
                'canonical',
                'address-1',
                ['governanceStatus' => $status, $inputKey => $linkId],
            );
            self::assertSame($status, $normalized['governance_status']);
            self::assertSame($linkId, $normalized[$column]);
        }

        $this->expectException(\RuntimeException::class);
        AddressGovernancePolicy::normalizePatch('duplicate', 'address-1', ['governanceStatus' => 'canonical']);
    }

    public function testValidationVerdictCoercesSupportedTransportValues(): void
    {
        self::assertNull(AddressValidationVerdict::fromArray(null));

        $payloads = [
            [['deliverable' => true, 'granularity' => ' premise ', 'quality' => 101, 'signal' => ['a' => 1]], true, 'premise', 100],
            [['deliverable' => 0, 'granularity' => '', 'quality' => -1.2, 'signal' => 'bad'], false, null, 0],
            [['deliverable' => 'yes', 'quality' => '77.6'], true, null, 78],
            [['deliverable' => 'no', 'quality' => 'bad'], false, null, null],
            [['deliverable' => new \stdClass(), 'granularity' => 12], null, null, null],
        ];

        foreach ($payloads as [$payload, $deliverable, $granularity, $quality]) {
            $verdict = AddressValidationVerdict::fromArray($payload);
            self::assertInstanceOf(AddressValidationVerdict::class, $verdict);
            self::assertSame($deliverable, $verdict->deliverable);
            self::assertSame($granularity, $verdict->granularity);
            self::assertSame($quality, $verdict->quality);
            self::assertSame($deliverable, $verdict->jsonSerialize()['deliverable']);
        }
    }

    public function testValidatedMessageExercisesNullAndTypedConversionPaths(): void
    {
        $empty = AddressValidated::fromArray([]);
        self::assertNull($empty->line1Norm);
        self::assertSame('canonical', $empty->governanceStatus);
        self::assertNull($empty->toDbArray()['validation_raw']);

        $validated = AddressValidated::fromArray([
            'line1Norm' => 123,
            'cityNorm' => ' Houston ',
            'latitude' => '29.7604',
            'longitude' => -95.3698,
            'validatedAt' => new \DateTime('2026-10-05T12:00:00+00:00'),
            'raw' => ['provider' => 'test'],
            'validationVerdict' => ['deliverable' => 1, 'quality' => 99],
            'sourceType' => 'validator',
            'revalidationDueAt' => 0,
            'revalidationPolicy' => 'weekly',
            'lastValidationStatus' => 'validated',
            'lastValidationScore' => '98',
        ]);

        self::assertSame('123', $validated->line1Norm);
        self::assertSame('Houston', $validated->cityNorm);
        self::assertSame(29.7604, $validated->latitude);
        self::assertSame(-95.3698, $validated->longitude);
        self::assertSame(98, $validated->lastValidationScore);
        self::assertSame(64, strlen($validated->fingerprint()));
        self::assertSame('{"provider":"test"}', $validated->toDbArray()['validation_raw']);
        self::assertSame('validated', $validated->jsonSerialize()['lastValidationStatus']);

        $invalid = AddressValidated::fromArray([
            'line1Norm' => [],
            'latitude' => new \stdClass(),
            'validatedAt' => new \stdClass(),
            'lastValidationScore' => '9.5',
            'verdict' => ['deliverable' => 'true'],
        ]);
        self::assertNull($invalid->line1Norm);
        self::assertNull($invalid->latitude);
        self::assertNull($invalid->validatedAt);
        self::assertNull($invalid->lastValidationScore);
        self::assertTrue($invalid->addressValidationVerdict?->deliverable);
    }
}
