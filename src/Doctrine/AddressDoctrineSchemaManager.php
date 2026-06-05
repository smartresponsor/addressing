<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\AddressEntity;
use App\Entity\AddressEvidenceSnapshotEntity;
use App\Entity\AddressOutboxEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

final readonly class AddressDoctrineSchemaManager
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function ensureSchema(): void
    {
        $tool = new SchemaTool($this->entityManager);
        $classes = $this->managedClasses();
        $tool->updateSchema($classes);
    }

    public function resetSchema(): void
    {
        $tool = new SchemaTool($this->entityManager);
        $classes = $this->managedClasses();
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    /**
     * @return list<\Doctrine\ORM\Mapping\ClassMetadata<object>>
     */
    private function managedClasses(): array
    {
        return [
            $this->entityManager->getClassMetadata(AddressEntity::class),
            $this->entityManager->getClassMetadata(AddressEvidenceSnapshotEntity::class),
            $this->entityManager->getClassMetadata(AddressOutboxEntity::class),
        ];
    }
}
