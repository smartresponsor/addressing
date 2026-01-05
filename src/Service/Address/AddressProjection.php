<?php
declare(strict_types=1);

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

namespace App\Service\Address;

use App\EntityInterface\Address\AddressInterface;
use App\ServiceInterface\Address\AddressProjectionInterface;
use PDO;

final class AddressProjection implements AddressProjectionInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function upsert(AddressInterface $a): void
    {
        $sql = <<<'SQL'
INSERT INTO address_projection
    (id, owner_id, vendor_id, line1, line2, city, region, postal_code, country_code,
     validation_status, created_at, updated_at, deleted_at)
VALUES
    (:id, :owner_id, :vendor_id, :line1, :line2, :city, :region, :postal_code, :country_code,
     :validation_status, :created_at, :updated_at, :deleted_at)
ON DUPLICATE KEY UPDATE
    owner_id=VALUES(owner_id),
    vendor_id=VALUES(vendor_id),
    line1=VALUES(line1),
    line2=VALUES(line2),
    city=VALUES(city),
    region=VALUES(region),
    postal_code=VALUES(postal_code),
    country_code=VALUES(country_code),
    validation_status=VALUES(validation_status),
    created_at=VALUES(created_at),
    updated_at=VALUES(updated_at),
    deleted_at=VALUES(deleted_at)
SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id' => $a->id(),
            ':owner_id' => $a->ownerId(),
            ':vendor_id' => $a->vendorId(),
            ':line1' => $a->line1(),
            ':line2' => $a->line2(),
            ':city' => $a->city(),
            ':region' => $a->region(),
            ':postal_code' => $a->postalCode(),
            ':country_code' => $a->countryCode(),
            ':validation_status' => $a->validationStatus(),
            ':created_at' => $a->createdAt(),
            ':updated_at' => $a->updatedAt(),
            ':deleted_at' => $a->deletedAt(),
        ]);
    }
}
