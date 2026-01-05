<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 * English comments only. No placeholders or stubs.
 */

namespace App\EntityInterface\Address;

interface AddressInterface
{
    public function id(): string;
    public function ownerId(): ?string;
    public function vendorId(): ?string;
    public function line1(): string;
    public function line2(): ?string;
    public function city(): string;
    public function region(): ?string;
    public function postalCode(): ?string;
    public function countryCode(): string;
    public function line1Norm(): ?string;
    public function cityNorm(): ?string;
    public function regionNorm(): ?string;
    public function postalCodeNorm(): ?string;
    public function latitude(): ?float;
    public function longitude(): ?float;
    public function geohash(): ?string;
    public function validationStatus(): string;
    public function validationProvider(): ?string;
    public function validatedAt(): ?string;
    public function dedupeKey(): ?string;
    public function createdAt(): string;
    public function updatedAt(): ?string;
    public function deletedAt(): ?string;
}