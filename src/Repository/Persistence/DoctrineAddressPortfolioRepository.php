<?php

declare(strict_types=1);

namespace App\Repository\Persistence;

use App\Entity\AddressEntity;
use App\RepositoryInterface\Persistence\AddressPortfolioRepositoryInterface;

final readonly class DoctrineAddressPortfolioRepository extends AbstractDoctrineAddressRepository implements AddressPortfolioRepositoryInterface
{
    #[\Override]
    public function summarizeCountryPortfolio(?string $ownerId, ?string $vendorId, ?string $q, array $filters = []): array
    {
        // @phpstan-ignore-next-line
        return $this->buildGroupedPortfolio(
            $this->fetchFilteredAddresses($ownerId, $vendorId, null, $q, $filters, true, true, true),
            static fn (AddressEntity $entity): string => $entity->getCountryCode(),
            /** @return array{countryCode:string} */
            static fn (AddressEntity $entity): array => [
                'countryCode' => $entity->getCountryCode(),
            ],
        );
    }

    #[\Override]
    public function summarizeSourcePortfolio(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, array $filters = []): array
    {
        $entities = $this->fetchFilteredAddresses($ownerId, $vendorId, $countryCode, $q, $filters, true, true, true);

        // @phpstan-ignore-next-line
        return $this->buildGroupedPortfolio(
            $entities,
            static fn (AddressEntity $entity): string => ($entity->getSourceSystem() ?? '').'|'.($entity->getSourceType() ?? ''),
            /** @return array{sourceSystem:string,sourceType:string} */
            static fn (AddressEntity $entity): array => [
                'sourceSystem' => $entity->getSourceSystem() ?? '',
                'sourceType' => $entity->getSourceType() ?? '',
            ],
        );
    }

    #[\Override]
    public function summarizeValidationPortfolio(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, array $filters = []): array
    {
        $entities = $this->fetchFilteredAddresses($ownerId, $vendorId, $countryCode, $q, $filters, true, true, true);

        // @phpstan-ignore-next-line
        return $this->buildGroupedPortfolio(
            $entities,
            static fn (AddressEntity $entity): string => ($entity->getLastValidationProvider() ?: $entity->getValidationProvider() ?: '').'|'.($entity->getLastValidationStatus() ?: $entity->getValidationStatus() ?: 'unknown'),
            /** @return array{validationProvider:string,validationStatus:string} */
            static fn (AddressEntity $entity): array => [
                'validationProvider' => $entity->getLastValidationProvider() ?: $entity->getValidationProvider() ?: '',
                'validationStatus' => $entity->getLastValidationStatus() ?: $entity->getValidationStatus() ?: 'unknown',
            ],
        );
    }

    #[\Override]
    public function summarizeNormalizationPortfolio(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, array $filters = []): array
    {
        $entities = $this->fetchFilteredAddresses($ownerId, $vendorId, $countryCode, $q, $filters, true, true, true);
        $expectedNormalizationVersion = $this->stringFilter($filters, 'expectedNormalizationVersion');

        // @phpstan-ignore-next-line
        return $this->buildGroupedPortfolio(
            $entities,
            static fn (AddressEntity $entity): string => ($entity->getNormalizationVersion() ?: '').'|'.($entity->getLastValidationStatus() ?: $entity->getValidationStatus() ?: 'unknown'),
            /** @return array{normalizationVersion:string,validationStatus:string,staleNormalization:int} */
            static fn (AddressEntity $entity): array => [
                'normalizationVersion' => $entity->getNormalizationVersion() ?: '',
                'validationStatus' => $entity->getLastValidationStatus() ?: $entity->getValidationStatus() ?: 'unknown',
                'staleNormalization' => null !== $expectedNormalizationVersion && (null === $entity->getNormalizationVersion() || $entity->getNormalizationVersion() !== $expectedNormalizationVersion) ? 1 : 0,
            ],
        );
    }
}
