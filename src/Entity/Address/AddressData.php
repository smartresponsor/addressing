<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 * Author: Oleksandr Tishchenko <dev@smartresponsor.com>
 * Owner: Marketing America Corp
 */
declare(strict_types=1);

namespace App\Entity\Address;

use App\EntityInterface\Address\AddressInterface;

/**
 *
 */

/**
 *
 */
final class AddressData implements AddressInterface
{
    /**
     * @param string $id
     * @param string|null $ownerId
     * @param string|null $vendorId
     * @param string $line1
     * @param string|null $line2
     * @param string $city
     * @param string|null $region
     * @param string|null $postalCode
     * @param string $countryCode
     * @param string|null $line1Norm
     * @param string|null $cityNorm
     * @param string|null $regionNorm
     * @param string|null $postalCodeNorm
     * @param float|null $latitude
     * @param float|null $longitude
     * @param string|null $geohash
     * @param string $validationStatus
     * @param string|null $validationProvider
     * @param string|null $validatedAt
     * @param string|null $dedupeKey
     * @param string $createdAt
     * @param string|null $updatedAt
     * @param string|null $deletedAt
     * @param string|null $validationFingerprint
     * @param array|null $validationRaw
     * @param array|null $validationVerdict
     * @param bool|null $validationDeliverable
     * @param string|null $validationGranularity
     * @param int|null $validationQuality
     */
    public function __construct(
        public string  $id,
        public ?string $ownerId,
        public ?string $vendorId,
        public string  $line1,
        public ?string $line2,
        public string  $city,
        public ?string $region,
        public ?string $postalCode,
        public string  $countryCode,
        public ?string $line1Norm,
        public ?string $cityNorm,
        public ?string $regionNorm,
        public ?string $postalCodeNorm,
        public ?float  $latitude,
        public ?float  $longitude,
        public ?string $geohash,
        public string  $validationStatus,
        public ?string $validationProvider,
        public ?string $validatedAt,
        public ?string $dedupeKey,
        public string  $createdAt,
        public ?string $updatedAt,
        public ?string $deletedAt,
        public ?string $validationFingerprint = null,
        /** @var array<string, mixed>|null */
        public ?array  $validationRaw = null,
        /** @var array<string, mixed>|null */
        public ?array  $validationVerdict = null,
        public ?bool   $validationDeliverable = null,
        public ?string $validationGranularity = null,
        public ?int    $validationQuality = null
    )
    {
    }

    /**
     * @return string
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function ownerId(): ?string
    {
        return $this->ownerId;
    }

    /**
     * @return string|null
     */
    public function vendorId(): ?string
    {
        return $this->vendorId;
    }

    /**
     * @return string
     */
    public function line1(): string
    {
        return $this->line1;
    }

    /**
     * @return string|null
     */
    public function line2(): ?string
    {
        return $this->line2;
    }

    /**
     * @return string
     */
    public function city(): string
    {
        return $this->city;
    }

    /**
     * @return string|null
     */
    public function region(): ?string
    {
        return $this->region;
    }

    /**
     * @return string|null
     */
    public function postalCode(): ?string
    {
        return $this->postalCode;
    }

    /**
     * @return string
     */
    public function countryCode(): string
    {
        return $this->countryCode;
    }

    /**
     * @return string|null
     */
    public function line1Norm(): ?string
    {
        return $this->line1Norm;
    }

    /**
     * @return string|null
     */
    public function cityNorm(): ?string
    {
        return $this->cityNorm;
    }

    /**
     * @return string|null
     */
    public function regionNorm(): ?string
    {
        return $this->regionNorm;
    }

    /**
     * @return string|null
     */
    public function postalCodeNorm(): ?string
    {
        return $this->postalCodeNorm;
    }

    /**
     * @return float|null
     */
    public function latitude(): ?float
    {
        return $this->latitude;
    }

    /**
     * @return float|null
     */
    public function longitude(): ?float
    {
        return $this->longitude;
    }

    /**
     * @return string|null
     */
    public function geohash(): ?string
    {
        return $this->geohash;
    }

    /**
     * @return string
     */
    public function validationStatus(): string
    {
        return $this->validationStatus;
    }

    /**
     * @return string|null
     */
    public function validationProvider(): ?string
    {
        return $this->validationProvider;
    }

    /**
     * @return string|null
     */
    public function validatedAt(): ?string
    {
        return $this->validatedAt;
    }

    /**
     * @return string|null
     */
    public function dedupeKey(): ?string
    {
        return $this->dedupeKey;
    }

    /**
     * @return string|null
     */
    public function validationFingerprint(): ?string
    {
        return $this->validationFingerprint;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validationRaw(): ?array
    {
        return $this->validationRaw;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validationVerdict(): ?array
    {
        return $this->validationVerdict;
    }

    /**
     * @return bool|null
     */
    public function validationDeliverable(): ?bool
    {
        return $this->validationDeliverable;
    }

    /**
     * @return string|null
     */
    public function validationGranularity(): ?string
    {
        return $this->validationGranularity;
    }

    /**
     * @return int|null
     */
    public function validationQuality(): ?int
    {
        return $this->validationQuality;
    }

    /**
     * @return string
     */
    public function createdAt(): string
    {
        return $this->createdAt;
    }

    /**
     * @return string|null
     */
    public function updatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * @return string|null
     */
    public function deletedAt(): ?string
    {
        return $this->deletedAt;
    }
}
