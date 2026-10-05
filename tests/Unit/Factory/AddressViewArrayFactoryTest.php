<?php

declare(strict_types=1);

namespace App\Addressing\Tests\Unit\Factory;

use App\Addressing\Contract\AddressInterface;
use App\Addressing\Factory\AddressViewArrayFactory;
use App\Addressing\Responder\AddressResponder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

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

    public function testResponderBuildsCanonicalJsonEnvelopes(): void
    {
        $address = $this->createStub(AddressInterface::class);
        $address->method('id')->willReturn('addr-1');
        $address->method('line1')->willReturn('123 Main St');
        $address->method('city')->willReturn('Houston');
        $address->method('countryCode')->willReturn('US');
        $address->method('validationStatus')->willReturn('valid');
        $address->method('governanceStatus')->willReturn('canonical');

        $responder = new AddressResponder(new AddressViewArrayFactory());

        $addressResponse = $responder->address($address);
        self::assertSame(Response::HTTP_OK, $addressResponse->getStatusCode());
        $addressPayload = json_decode((string) $addressResponse->getContent(), true);
        self::assertIsArray($addressPayload);
        self::assertSame('addr-1', $addressPayload['id'] ?? null);

        $summaryResponse = $responder->summaryItems([['countryCode' => 'US', 'total' => 2]]);
        self::assertSame(['items' => [['countryCode' => 'US', 'total' => 2]]], json_decode((string) $summaryResponse->getContent(), true));

        $notFound = $responder->notFound();
        self::assertSame(Response::HTTP_NOT_FOUND, $notFound->getStatusCode());
        self::assertSame(['error' => 'not_found'], json_decode((string) $notFound->getContent(), true));

        $invalid = $responder->invalidRequest(new \RuntimeException('bad input'), 'validation_failed', 422);
        self::assertSame(422, $invalid->getStatusCode());
        self::assertSame([
            'error' => 'validation_failed',
            'message' => 'bad input',
        ], json_decode((string) $invalid->getContent(), true));
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
