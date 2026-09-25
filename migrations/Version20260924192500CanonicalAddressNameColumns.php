<?php

declare(strict_types=1);

namespace App\Addressing\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924192500CanonicalAddressNameColumns extends AbstractMigration
{
    /** @var list<string> */
    private const array TABLES = [
        'address_city',
        'address_country',
        'address_province',
    ];

    public function getDescription(): string
    {
        return 'Rename legacy mixed-case Addressing nameEntity columns to canonical name_entity.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Addressing canonical name-column migration supports PostgreSQL only.',
        );

        foreach (self::TABLES as $table) {
            $this->addSql(sprintf(
                <<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = '%1$s'
          AND column_name = 'nameEntity'
    ) AND NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = '%1$s'
          AND column_name = 'name_entity'
    ) THEN
        EXECUTE 'ALTER TABLE %1$s RENAME COLUMN "nameEntity" TO name_entity';
    ELSIF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = '%1$s'
          AND column_name = 'nameEntity'
    ) AND EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = '%1$s'
          AND column_name = 'name_entity'
    ) THEN
        RAISE EXCEPTION 'Both legacy and canonical name columns exist on %1$s; manual reconciliation is required.';
    END IF;
END
$$
SQL,
                $table,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Canonical Addressing physical column naming is intentionally irreversible.',
        );
    }
}
