<?php

declare(strict_types=1);

namespace App\Addressing\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260923131500ObjectingIdentityConstraintNames extends AbstractMigration
{
    /** @var array<string, string> */
    private const array INDEX_RENAMES = [
        'uniq_5017d2dfd17f50a6' => 'uniq_address_city_uuid',
        'uniq_5017d2df989d9b62' => 'uniq_address_city_slug',
        'uniq_d3bf9d1fd17f50a6' => 'uniq_address_component_uuid',
        'uniq_d3bf9d1f989d9b62' => 'uniq_address_component_slug',
        'uniq_8963efed17f50a6' => 'uniq_address_country_uuid',
        'uniq_8963efe989d9b62' => 'uniq_address_country_slug',
        'uniq_e5e3f760d17f50a6' => 'uniq_address_format_uuid',
        'uniq_e5e3f760989d9b62' => 'uniq_address_format_slug',
        'uniq_fd57c0bdd17f50a6' => 'uniq_address_postal_code_uuid',
        'uniq_fd57c0bd989d9b62' => 'uniq_address_postal_code_slug',
        'uniq_b4552a8ad17f50a6' => 'uniq_address_province_uuid',
        'uniq_b4552a8a989d9b62' => 'uniq_address_province_slug',
        'uniq_cbb75667d17f50a6' => 'uniq_address_street_uuid',
        'uniq_cbb75667989d9b62' => 'uniq_address_street_slug',
        'uniq_fc903a01d17f50a6' => 'uniq_address_street_type_uuid',
        'uniq_fc903a01989d9b62' => 'uniq_address_street_type_slug',
    ];

    public function getDescription(): string
    {
        return 'Replace hash-derived Addressing Objecting identity unique indexes with deterministic semantic names.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Addressing Objecting identity constraint migration supports PostgreSQL only.',
        );

        foreach (self::INDEX_RENAMES as $legacy => $canonical) {
            $this->addSql(sprintf(
                <<<'SQL'
DO $$
BEGIN
    IF to_regclass('public.%1$s') IS NOT NULL AND to_regclass('public.%2$s') IS NULL THEN
        EXECUTE 'ALTER INDEX %1$s RENAME TO %2$s';
    ELSIF to_regclass('public.%1$s') IS NOT NULL AND to_regclass('public.%2$s') IS NOT NULL THEN
        RAISE EXCEPTION 'Both legacy index %1$s and canonical index %2$s exist; manual reconciliation is required.';
    END IF;
END
$$
SQL,
                $legacy,
                $canonical,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Deterministic Addressing Objecting identity constraint naming is intentionally irreversible.',
        );
    }
}

