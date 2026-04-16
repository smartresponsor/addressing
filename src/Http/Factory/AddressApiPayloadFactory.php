<?php

declare(strict_types=1);

namespace App\Http\Factory;

use App\Contract\Message\AddressRecordPolicy;
use App\Contract\Message\AddressValidated;
use App\Entity\Record\AddressData;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Ulid;

final readonly class AddressApiPayloadFactory
{
    /** @return array<string, mixed> */
    public function decodeJsonRequest(Request $request): array
    {
        $raw = $request->getContent();
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('invalid_json');
        }

        return $data;
    }

    /** @param array<string, mixed> $in */
    public function createAddressData(array $in): AddressData
    {
        $id = (string) new Ulid();
        $createdAt = new \DateTimeImmutable('now');
        $now = $createdAt->format('Y-m-d H:i:sP');

        return new AddressData(
            $id,
            $this->optStr($in, 'ownerId'),
            $this->optStr($in, 'vendorId'),
            $this->reqStr($in, 'line1'),
            $this->optStr($in, 'line2'),
            $this->reqStr($in, 'city'),
            $this->optStr($in, 'region'),
            $this->optStr($in, 'postalCode'),
            strtoupper($this->reqStr($in, 'countryCode')),
            $this->optStr($in, 'line1Norm'),
            $this->optStr($in, 'cityNorm'),
            $this->optStr($in, 'regionNorm'),
            $this->optStr($in, 'postalCodeNorm'),
            $this->optFloat($in, 'latitude'),
            $this->optFloat($in, 'longitude'),
            $this->optStr($in, 'geohash'),
            AddressRecordPolicy::normalizeValidationStatus($this->optStr($in, 'validationStatus'), 'pending'),
            $this->optStr($in, 'validationProvider'),
            $this->optStr($in, 'validatedAt'),
            $this->optStr($in, 'dedupeKey'),
            $now,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $this->optStr($in, 'sourceSystem'),
            AddressRecordPolicy::normalizeSourceType($this->optStr($in, 'sourceType')),
            $this->optStr($in, 'sourceReference'),
            $this->optStr($in, 'normalizationVersion'),
            $this->optArray($in, 'rawInputSnapshot'),
            $this->optArray($in, 'normalizedSnapshot'),
            $this->optStr($in, 'providerDigest'),
            AddressRecordPolicy::normalizeGovernanceStatus($this->optStr($in, 'governanceStatus') ?? 'canonical'),
            $this->optStr($in, 'duplicateOfId'),
            $this->optStr($in, 'supersededById'),
            $this->optStr($in, 'aliasOfId'),
            $this->optStr($in, 'conflictWithId'),
            $this->optStr($in, 'revalidationDueAt'),
            AddressRecordPolicy::normalizeRevalidationPolicy($this->optStr($in, 'revalidationPolicy')),
            $this->optStr($in, 'lastValidationProvider'),
            AddressRecordPolicy::normalizeLastValidationStatus($this->optStr($in, 'lastValidationStatus')),
            $this->lastValidationScore($in)
        );
    }

    /** @param array<string, mixed> $in */
    public function createAddressValidated(array $in): AddressValidated
    {
        return AddressValidated::fromArray($this->validatedPayload($in));
    }

    /**
     * @param array<string, mixed> $in
     *
     * @return array{
     *   'governanceStatus':?string,
     *   'duplicateOfId':?string,
     *   'supersededById':?string,
     *   'aliasOfId':?string,
     *   'conflictWithId':?string,
     *   'revalidationDueAt':?string,
     *   'revalidationPolicy':?string,
     *   'lastValidationProvider':?string,
     *   'lastValidationStatus':?string,
     *   lastValidationScore:?int
     * }
     */
    public function operationalPatch(array $in): array
    {
        return [
            'governanceStatus' => $this->optStr($in, 'governanceStatus'),
            'duplicateOfId' => $this->optStr($in, 'duplicateOfId'),
            'supersededById' => $this->optStr($in, 'supersededById'),
            'aliasOfId' => $this->optStr($in, 'aliasOfId'),
            'conflictWithId' => $this->optStr($in, 'conflictWithId'),
            'revalidationDueAt' => $this->optStr($in, 'revalidationDueAt'),
            'revalidationPolicy' => $this->optStr($in, 'revalidationPolicy'),
            'lastValidationProvider' => $this->optStr($in, 'lastValidationProvider'),
            'lastValidationStatus' => $this->optStr($in, 'lastValidationStatus'),
            'lastValidationScore' => $this->lastValidationScore($in),
        ];
    }

    /**
     * @param array<string, mixed> $in
     *
     * @return list<string>
     */
    public function requireStringList(array $in, string $key): array
    {
        if (!array_key_exists($key, $in) || !is_array($in[$key])) {
            throw new \RuntimeException('missing_'.$key);
        }

        $values = [];
        foreach ($in[$key] as $item) {
            if (!is_string($item) || '' === trim($item)) {
                throw new \RuntimeException('invalid_'.$key);
            }
            $values[] = trim($item);
        }

        if ([] === $values) {
            throw new \RuntimeException('invalid_'.$key);
        }

        return array_values(array_unique($values));
    }

    /** @param array<string, mixed> $in */
    private function reqStr(array $in, string $key): string
    {
        if (!array_key_exists($key, $in) || !is_string($in[$key]) || '' === trim($in[$key])) {
            throw new \RuntimeException('missing_'.$key);
        }

        return trim($in[$key]);
    }

    /** @param array<string, mixed> $in */
    private function optStr(array $in, string $key): ?string
    {
        if (!array_key_exists($key, $in) || null === $in[$key]) {
            return null;
        }
        if (!is_string($in[$key])) {
            throw new \RuntimeException('invalid_'.$key);
        }
        $value = trim($in[$key]);

        return '' === $value ? null : $value;
    }

    /**
     * @param array<string, mixed> $in
     *
     * @return array<string, mixed>|null
     */
    private function optArray(array $in, string $key): ?array
    {
        if (!array_key_exists($key, $in) || null === $in[$key]) {
            return null;
        }
        if (!is_array($in[$key])) {
            throw new \RuntimeException('invalid_'.$key);
        }

        return $in[$key];
    }

    /** @param array<string, mixed> $in */
    private function lastValidationScore(array $in): ?int
    {
        $key = 'lastValidationScore';
        if (!array_key_exists($key, $in) || null === $in[$key] || '' === $in[$key]) {
            return null;
        }
        if (is_int($in[$key])) {
            return $in[$key];
        }
        if (is_string($in[$key]) && is_numeric($in[$key])) {
            return (int) $in[$key];
        }
        throw new \RuntimeException('invalid_'.$key);
    }

    /** @param array<string, mixed> $in */
    private function optFloat(array $in, string $key): ?float
    {
        if (!array_key_exists($key, $in) || null === $in[$key] || '' === $in[$key]) {
            return null;
        }
        if (is_int($in[$key]) || is_float($in[$key])) {
            return (float) $in[$key];
        }
        if (is_string($in[$key]) && is_numeric($in[$key])) {
            return (float) $in[$key];
        }
        throw new \RuntimeException('invalid_'.$key);
    }

    /**
     * @param array<string, mixed> $in
     *
     * @return array<string, mixed>
     */
    private function validatedPayload(array $in): array
    {
        return [
            'line1Norm' => $this->optStr($in, 'line1Norm'),
            'cityNorm' => $this->optStr($in, 'cityNorm'),
            'regionNorm' => $this->optStr($in, 'regionNorm'),
            'postalCodeNorm' => $this->optStr($in, 'postalCodeNorm'),
            'latitude' => $this->optFloat($in, 'latitude'),
            'longitude' => $this->optFloat($in, 'longitude'),
            'geohash' => $this->optStr($in, 'geohash'),
            'validationProvider' => $this->validationProviderInput($in),
            'validatedAt' => $this->optStr($in, 'validatedAt'),
            'dedupeKey' => $this->optStr($in, 'dedupeKey'),
            'sourceSystem' => $this->optStr($in, 'sourceSystem'),
            'sourceType' => $this->optStr($in, 'sourceType'),
            'sourceReference' => $this->optStr($in, 'sourceReference'),
            'normalizationVersion' => $this->optStr($in, 'normalizationVersion'),
            'rawInput' => $this->optArray($in, 'rawInput'),
            'normalizedSnapshot' => $this->optArray($in, 'normalizedSnapshot'),
            'providerDigest' => $this->optStr($in, 'providerDigest'),
            'governanceStatus' => $this->optStr($in, 'governanceStatus'),
            'duplicateOfId' => $this->optStr($in, 'duplicateOfId'),
            'supersededById' => $this->optStr($in, 'supersededById'),
            'aliasOfId' => $this->optStr($in, 'aliasOfId'),
            'conflictWithId' => $this->optStr($in, 'conflictWithId'),
            'revalidationDueAt' => $this->optStr($in, 'revalidationDueAt'),
            'revalidationPolicy' => $this->optStr($in, 'revalidationPolicy'),
            'lastValidationProvider' => $this->optStr($in, 'lastValidationProvider'),
            'lastValidationStatus' => $this->optStr($in, 'lastValidationStatus'),
            'lastValidationScore' => $this->lastValidationScore($in),
        ];
    }

    /** @param array<string, mixed> $in */
    private function validationProviderInput(array $in): ?string
    {
        return $this->optStr($in, 'provider') ?? $this->optStr($in, 'validationProvider');
    }
}
