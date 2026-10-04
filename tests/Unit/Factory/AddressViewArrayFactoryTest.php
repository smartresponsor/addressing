<?php

declare(strict_types=1);

namespace App\Addressing\Tests\Unit\Factory;

use App\Addressing\Contract\AddressInterface;
use App\Addressing\Factory\AddressViewArrayFactory;
use PHPUnit\Framework\TestCase;

final class AddressViewArrayFactoryTest extends TestCase
{
    public function testGovernanceConflictHasHighestReviewPriority(): void
    {
        $address = $this->createStub(AddressInterface::class);
        $address->method('governanceStatus')->willReturn('conflict');
        $address->method('validationStatus')->willReturn('uncertain');
        $address->method('lastValidationStatus')->willReturn('uncertain');
        $address->method('duplicateOfId')->willReturn('01HZZZZZZZZZZZZZZZZZZZZZZZ');
        $address->method('revalidationDueAt')->willReturn('2000-01-01T00:00:00+00:00');
        $address->method('normalizationVersion')->willReturn('v1');

        $payload = (new AddressViewArrayFactory())->toArray($address, 'v2');

        self::assertTrue($payload['requiresReview']);
        self::assertSame('governanceConflict', $payload['reviewReason']);
        self::assertTrue($payload['isGovernanceConflict']);
        self::assertTrue($payload['isValidationUncertain']);
        self::assertTrue($payload['isEvidenceMissing']);
        self::assertTrue($payload['isRevalidationDue']);
        self::assertTrue($payload['isNormalizationStale']);
        self::assertSame('01HZZZZZZZZZZZZZZZZZZZZZZZ', $payload['governanceLinkId']);
        self::assertTrue($payload['hasGovernanceLink']);
    }

    public function testEvidenceBackedCurrentAddressDoesNotRequireReview(): void
    {
        $address = $this->createStub(AddressInterface::class);
        $address->method('governanceStatus')->willReturn('canonical');
        $address->method('validationStatus')->willReturn('valid');
        $address->method('lastValidationStatus')->willReturn('valid');
        $address->method('providerDigest')->willReturn('sha256:provider-evidence');
        $address->method('normalizationVersion')->willReturn('v2');

        $payload = (new AddressViewArrayFactory())->toArray($address, 'v2');

        self::assertTrue($payload['hasEvidence']);
        self::assertFalse($payload['isEvidenceMissing']);
        self::assertFalse($payload['requiresReview']);
        self::assertNull($payload['reviewReason']);
        self::assertFalse($payload['isRevalidationDue']);
        self::assertFalse($payload['isNormalizationStale']);
        self::assertFalse($payload['hasGovernanceLink']);
    }

    public function testDueRevalidationPrecedesStaleNormalization(): void
    {
        $address = $this->createStub(AddressInterface::class);
        $address->method('governanceStatus')->willReturn('canonical');
        $address->method('validationStatus')->willReturn('valid');
        $address->method('lastValidationStatus')->willReturn('valid');
        $address->method('providerDigest')->willReturn('sha256:provider-evidence');
        $address->method('revalidationDueAt')->willReturn('2000-01-01T00:00:00+00:00');
        $address->method('normalizationVersion')->willReturn('v1');

        $payload = (new AddressViewArrayFactory())->toArray($address, 'v2');

        self::assertTrue($payload['isRevalidationDue']);
        self::assertTrue($payload['isNormalizationStale']);
        self::assertSame('dueForRevalidation', $payload['reviewReason']);
    }
}
