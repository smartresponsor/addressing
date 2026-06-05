<?php

declare(strict_types=1);

namespace App\Repository\Persistence;

use App\RepositoryInterface\Persistence\AddressQueueRepositoryInterface;

final readonly class DoctrineAddressQueueRepository extends AbstractDoctrineAddressRepository implements AddressQueueRepositoryInterface
{
    #[\Override]
    public function summarizeOperationalQueues(?string $ownerId, ?string $vendorId, ?string $countryCode, ?string $q, array $filters = []): array
    {
        $entities = $this->fetchFilteredAddresses($ownerId, $vendorId, $countryCode, $q, $filters, true, true, true);
        $queueParams = [];
        $this->summaryDueBefore($queueParams, $filters);
        $queueDueBefore = (string) ($queueParams['summary_due_before'] ?? $this->currentTimestampAtom());
        $expectedNormalizationVersion = $this->stringFilter($filters, 'expectedNormalizationVersion');

        $row = [
            'total' => 0,
            'due_for_revalidation' => 0,
            'evidence_missing' => 0,
            'uncertain_validation' => 0,
            'conflict_review' => 0,
            'duplicate_review' => 0,
            'stale_normalization_version' => 0,
        ];

        foreach ($entities as $entity) {
            ++$row['total'];
            if (null !== $entity->getRevalidationDueAt() && $entity->getRevalidationDueAt()->format(DATE_ATOM) <= $queueDueBefore) {
                ++$row['due_for_revalidation'];
            }
            if (!$this->hasEvidenceEntity($entity)) {
                ++$row['evidence_missing'];
            }
            if ('uncertain' === $entity->getValidationStatus() || 'uncertain' === $entity->getLastValidationStatus()) {
                ++$row['uncertain_validation'];
            }
            if ('conflict' === $entity->getGovernanceStatus()) {
                ++$row['conflict_review'];
            }
            if ('duplicate' === $entity->getGovernanceStatus()) {
                ++$row['duplicate_review'];
            }
            if (null !== $expectedNormalizationVersion && (null === $entity->getNormalizationVersion() || $entity->getNormalizationVersion() !== $expectedNormalizationVersion)) {
                ++$row['stale_normalization_version'];
            }
        }

        return [
            'total' => $row['total'],
            'dueForRevalidation' => $row['due_for_revalidation'],
            'evidenceMissing' => $row['evidence_missing'],
            'uncertainValidation' => $row['uncertain_validation'],
            'conflictReview' => $row['conflict_review'],
            'duplicateReview' => $row['duplicate_review'],
            'staleNormalizationVersion' => $row['stale_normalization_version'],
        ];
    }
}
