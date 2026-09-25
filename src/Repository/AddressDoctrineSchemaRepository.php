<?php

declare(strict_types=1);

namespace App\Addressing\Repository;

use App\Addressing\Entity\AddressEntity;
use App\Addressing\Entity\AddressEvidenceSnapshotEntity;
use App\Addressing\Entity\AddressOutboxEntity;
use App\Addressing\RepositoryInterface\AddressSchemaRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

final readonly class AddressDoctrineSchemaRepository implements AddressSchemaRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function ensureSchema(): void
    {
        (new SchemaTool($this->entityManager))->updateSchema($this->managedClasses());
    }

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
