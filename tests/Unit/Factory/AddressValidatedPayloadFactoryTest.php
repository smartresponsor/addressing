<?php

declare(strict_types=1);

namespace App\Addressing\Tests\Unit\Factory;

use App\Addressing\Context\Application\AddressValidatedOutboxContext;
use App\Addressing\Contract\Message\AddressValidated;
use App\Addressing\Factory\Application\AddressValidatedPayloadFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AddressValidatedPayloadFactoryTest extends TestCase
{
    /** @param array<string, mixed> $payload */
    #[DataProvider('evidencePayloadProvider')]
    public function testHasEvidenceRecognizesSupportedEvidenceSources(array $payload): void
    {
        $factory = new AddressValidatedPayloadFactory();

        self::assertTrue($factory->hasEvidence(AddressValidated::fromArray($payload)));
    }

    /** @return iterable<string, array{0: array<string, mixed>}> */
    public static function evidencePayloadProvider(): iterable
    {
        yield 'raw input' => [['rawInput' => ['line1' => '123 Main St']]];
        yield 'normalized snapshot' => [['normalizedSnapshot' => ['line1Norm' => '123 MAIN ST']]];
        yield 'provider digest' => [['providerDigest' => 'digest']];
        yield 'raw provider payload' => [['raw' => ['status' => 'verified']]];
        yield 'verdict' => [['verdict' => ['deliverable' => true]]];
    }

    public function testHasEvidenceReturnsFalseWithoutEvidence(): void
    {
        self::assertFalse((new AddressValidatedPayloadFactory())->hasEvidence(AddressValidated::fromArray([])));
    }

    public function testNormalizedSnapshotUsesExplicitSnapshotOrDerivesNormalizedFields(): void
    {
        $factory = new AddressValidatedPayloadFactory();

        self::assertSame(
            ['explicit' => true],
            $factory->normalizedSnapshot(AddressValidated::fromArray(['normalizedSnapshot' => ['explicit' => true]])),
        );
        self::assertSame(
            ['line1Norm' => '123 MAIN ST', 'cityNorm' => 'HOUSTON', 'latitude' => 29.76],
            $factory->normalizedSnapshot(AddressValidated::fromArray([
                'line1Norm' => '123 MAIN ST',
                'cityNorm' => 'HOUSTON',
                'latitude' => 29.76,
            ])),
        );
        self::assertNull($factory->normalizedSnapshot(AddressValidated::fromArray([])));
    }

    public function testProviderDigestUsesExplicitDigestOrDeterministicEvidenceHash(): void
    {
        $factory = new AddressValidatedPayloadFactory();
        $validated = AddressValidated::fromArray([
            'validationProvider' => 'unit',
            'validatedAt' => '2026-10-05T01:00:00+00:00',
            'raw' => ['status' => 'verified'],
            'verdict' => ['deliverable' => true, 'quality' => 95],
        ]);

        self::assertSame('provided-digest', $factory->providerDigest(AddressValidated::fromArray(['providerDigest' => 'provided-digest'])));
        self::assertSame($factory->providerDigest($validated), $factory->providerDigest($validated));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $factory->providerDigest($validated) ?? '');
        self::assertNull($factory->providerDigest(AddressValidated::fromArray([])));
    }

    public function testSanitizeGovernanceLinkRejectsBlankAndSelfReferences(): void
    {
        $factory = new AddressValidatedPayloadFactory();

        self::assertNull($factory->sanitizeGovernanceLink(null, 'current'));
        self::assertNull($factory->sanitizeGovernanceLink('  ', 'current'));
        self::assertNull($factory->sanitizeGovernanceLink('current', 'current'));
        self::assertSame('other', $factory->sanitizeGovernanceLink(' other ', 'current'));
    }

    #[DataProvider('governanceLinkProvider')]
    public function testOutboxPayloadSelectsGovernanceLink(string $status, ?string $expectedLink): void
    {
        $factory = new AddressValidatedPayloadFactory();
        $validated = AddressValidated::fromArray([
            'validationProvider' => 'unit',
            'sourceType' => 'validator',
            'raw' => ['status' => 'verified'],
            'verdict' => ['deliverable' => true, 'granularity' => 'premise', 'quality' => 95],
        ]);
        $context = new AddressValidatedOutboxContext(
            id: 'address-1',
            ownerId: 'owner-1',
            vendorId: 'vendor-1',
            fingerprint: 'fingerprint-1',
            validatedAt: new \DateTimeImmutable('2026-10-05T01:00:00+00:00'),
            rawSha256: 'raw-sha',
            governanceStatus: $status,
            duplicateOfId: 'duplicate-1',
            supersededById: 'superseded-1',
            aliasOfId: 'alias-1',
            conflictWithId: 'conflict-1',
            revalidationDueAt: '2026-11-05T01:00:00+00:00',
            revalidationPolicy: 'monthly',
            lastValidationStatus: 'validated',
            lastValidationScore: 95,
            evidenceSnapshotId: 'snapshot-1',
            providerDigest: 'digest-1',
        );

        $payload = $factory->outboxPayload($context, $validated);

        self::assertSame('address-1', $payload['id']);
        self::assertSame('unit', $payload['provider']);
        self::assertTrue($payload['deliverable']);
        self::assertSame('premise', $payload['granularity']);
        self::assertSame(95, $payload['quality']);
        self::assertTrue($payload['hasEvidence']);
        self::assertSame($status, $payload['governanceStatus']);
        self::assertSame($expectedLink, $payload['governanceLinkId']);
        self::assertSame('snapshot-1', $payload['evidenceSnapshotId']);
    }

    /** @return iterable<string, array{0: string, 1: string|null}> */
    public static function governanceLinkProvider(): iterable
    {
        yield 'duplicate' => ['duplicate', 'duplicate-1'];
        yield 'superseded' => ['superseded', 'superseded-1'];
        yield 'alias' => ['alias', 'alias-1'];
        yield 'conflict' => ['conflict', 'conflict-1'];
        yield 'canonical' => ['canonical', null];
    }
}
