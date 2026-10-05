<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\Entity\AddressEvidenceSnapshotEntity;
use App\Addressing\Entity\AddressOutboxEntity;
use App\Addressing\RepositoryInterface\AddressSchemaRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Manages the standalone Doctrine schema for Addressing records, evidence snapshots, and outbox persistence.
 */
final readonly class AddressDoctrineSchemaRepository implements AddressSchemaRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** Create or update the managed standalone Addressing schema without dropping existing data. */
    public function ensureSchema(): void
    {
        (new SchemaTool($this->entityManager))->updateSchema($this->managedClasses());
    }

    /** Drop and recreate the managed standalone Addressing schema for controlled reset workflows. */
    public function resetSchema(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $classes = $this->managedClasses();
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);
    }

    /** @return list<\Doctrine\ORM\Mapping\ClassMetadata<object>> */
    private function managedClasses(): array
    {
        return [
            $this->entityManager->getClassMetadata(AddressEntity::class),
            $this->entityManager->getClassMetadata(AddressEvidenceSnapshotEntity::class),
            $this->entityManager->getClassMetadata(AddressOutboxEntity::class),
        ];
    }
}
