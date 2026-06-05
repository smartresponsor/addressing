<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'address_index')]
#[ORM\Index(name: 'idx_country_postal', columns: ['country', 'postal'])]
#[ORM\Index(name: 'idx_city', columns: ['city'])]
#[ORM\Index(name: 'idx_region', columns: ['region'])]
#[ORM\Index(name: 'idx_geo', columns: ['geo_key'])]
class AddressIndexEntity
{
    #[ORM\Id]
    #[ORM\Column(name: 'digest', type: 'string', length: 64)]
    private string $digest;

    #[ORM\Column(name: 'line1', type: 'string', length: 160)]
    private string $line1;

    #[ORM\Column(name: 'line2', type: 'string', length: 160, nullable: true)]
    private ?string $line2 = null;

    #[ORM\Column(name: 'city', type: 'string', length: 160)]
    private string $city;

    #[ORM\Column(name: 'region', type: 'string', length: 32)]
    private string $region;

    #[ORM\Column(name: 'postal', type: 'string', length: 32)]
    private string $postal;

    #[ORM\Column(name: 'country', type: 'string', length: 2)]
    private string $country;

    #[ORM\Column(name: 'lat', type: 'float', nullable: true)]
    private ?float $lat = null;

    #[ORM\Column(name: 'lon', type: 'float', nullable: true)]
    private ?float $lon = null;

    #[ORM\Column(name: 'display', type: 'string', length: 255, nullable: true)]
    private ?string $display = null;

    #[ORM\Column(name: 'provider', type: 'string', length: 64, nullable: true)]
    private ?string $provider = null;

    #[ORM\Column(name: 'confidence', type: 'float', nullable: true)]
    private ?float $confidence = null;

    #[ORM\Column(name: 'geo_key', type: 'string', length: 64, options: ['default' => ''])]
    private string $geoKey = '';

    #[ORM\Column(name: 'created_at', type: 'string', length: 32)]
    private string $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'string', length: 32)]
    private string $updatedAt;

    public function getDigest(): string
    {
        return $this->digest;
    }

    public function setDigest(string $digest): self
    {
        $this->digest = $digest;

        return $this;
    }

    public function getLine1(): string
    {
        return $this->line1;
    }

    public function setLine1(string $line1): self
    {
        $this->line1 = $line1;

        return $this;
    }

    public function getLine2(): ?string
    {
        return $this->line2;
    }

    public function setLine2(?string $line2): self
    {
        $this->line2 = $line2;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function setRegion(string $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function getPostal(): string
    {
        return $this->postal;
    }

    public function setPostal(string $postal): self
    {
        $this->postal = $postal;

        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function setLat(?float $lat): self
    {
        $this->lat = $lat;

        return $this;
    }

    public function getLon(): ?float
    {
        return $this->lon;
    }

    public function setLon(?float $lon): self
    {
        $this->lon = $lon;

        return $this;
    }

    public function getDisplay(): ?string
    {
        return $this->display;
    }

    public function setDisplay(?string $display): self
    {
        $this->display = $display;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function setConfidence(?float $confidence): self
    {
        $this->confidence = $confidence;

        return $this;
    }

    public function getGeoKey(): string
    {
        return $this->geoKey;
    }

    public function setGeoKey(string $geoKey): self
    {
        $this->geoKey = $geoKey;

        return $this;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(string $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(string $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
