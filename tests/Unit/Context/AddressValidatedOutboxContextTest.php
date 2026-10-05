<?php

declare(strict_types=1);

namespace App\Addressing\Tests\Unit\Context;

use App\Addressing\Context\Application\AddressValidatedOutboxContext;
use App\Addressing\Plan\Persistence\AddressValidatedMutationPlan;
use PHPUnit\Framework\TestCase;

final class AddressValidatedOutboxContextTest extends TestCase
{
    public function testFromMutationPlanCopiesValidatedPersistenceState(): void
    {
        $plan = new AddressValidatedMutationPlan(
            updateAssignments: ['fingerprint = :fingerprint', 'governance_status = :governance_status'],
            params: ['fingerprint' => 'fingerprint-1', 'governance_status' => 'duplicate'],
            governanceStatus: 'duplicate',
            duplicateOfId: 'address-duplicate',
            supersededById: null,
            aliasOfId: null,
            conflictWithId: null,
            normalizedSnapshot: ['line1Norm' => '123 MAIN ST'],
            providerDigest: 'provider-digest',
            lastValidationStatus: 'validated',
            lastValidationScore: 97,
            revalidationDueAt: '2026-11-05T12:00:00+00:00',
            revalidationPolicy: 'monthly',
            rawSha256: 'raw-sha-256',
        );
        $validatedAt = new \DateTimeImmutable('2026-10-05T12:00:00+00:00');

        $context = AddressValidatedOutboxContext::fromMutationPlan(
            id: 'address-1',
            ownerId: 'owner-1',
            vendorId: 'vendor-1',
            fingerprint: 'fingerprint-1',
            validatedAt: $validatedAt,
            evidenceSnapshotId: 'snapshot-1',
            plan: $plan,
        );

        self::assertSame('address-1', $context->id);
        self::assertSame('owner-1', $context->ownerId);
        self::assertSame('vendor-1', $context->vendorId);
        self::assertSame('fingerprint-1', $context->fingerprint);
        self::assertSame($validatedAt, $context->validatedAt);
        self::assertSame('raw-sha-256', $context->rawSha256);
        self::assertSame('duplicate', $context->governanceStatus);
        self::assertSame('address-duplicate', $context->duplicateOfId);
        self::assertNull($context->supersededById);
        self::assertNull($context->aliasOfId);
        self::assertNull($context->conflictWithId);
        self::assertSame('2026-11-05T12:00:00+00:00', $context->revalidationDueAt);
        self::assertSame('monthly', $context->revalidationPolicy);
        self::assertSame('validated', $context->lastValidationStatus);
        self::assertSame(97, $context->lastValidationScore);
        self::assertSame('snapshot-1', $context->evidenceSnapshotId);
        self::assertSame('provider-digest', $context->providerDigest);
        self::assertSame('fingerprint = :fingerprint, governance_status = :governance_status', $plan->setClause());
    }
}
