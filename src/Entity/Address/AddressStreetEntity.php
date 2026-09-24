<?php

declare(strict_types=1);

namespace App\Addressing\Entity\Address;

use App\Objecting\EntityTrait\Embeddable\ObjectAuditEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectIdentityEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectStateEmbeddableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Addressing\Repository\AddressStreetEntityRepository::class)]
#[ORM\Table(name: 'address_street')]
#[ORM\Index(name: 'address_street_lookup_idx', columns: ['country_code', 'city_name', 'street_name'])]
class AddressStreetEntity
{
    use ObjectIdentityEmbeddableTrait;
    use ObjectAuditEmbeddableTrait;
    use ObjectStateEmbeddableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'country_code', type: 'string', length: 2)]
    private string $countryCode = 'US';

    #[ORM\Column(name: 'province_code', type: 'string', length: 32, nullable: true)]
    private ?string $provinceCode = null;

    #[ORM\Column(name: 'city_name', type: 'string', length: 128, nullable: true)]
    private ?string $cityName = null;

    #[ORM\Column(name: 'street_name', type: 'string', length: 160)]
    private string $streetName = '';

    #[ORM\Column(name: 'street_type', type: 'string', length: 32, nullable: true)]
    private ?string $streetType = null;

    #[ORM\Column(name: 'normalized_name', type: 'string', length: 160, nullable: true)]
    private ?string $normalizedName = null;

    public function __construct(?string $objectUuid = null, ?string $objectSlug = null)
    {
        $this->initializeObjectIdentity($objectUuid, $objectSlug);
        $this->initializeObjectAudit();
        $this->initializeObjectState();
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): self
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    public function getProvinceCode(): ?string
    {
        return $this->provinceCode;
    }

    public function setProvinceCode(?string $provinceCode): self
    {
        $this->provinceCode = $provinceCode;

        return $this;
    }

    public function getCityName(): ?string
    {
        return $this->cityName;
    }

    public function setCityName(?string $cityName): self
    {
        $this->cityName = $cityName;

        return $this;
    }

    public function getStreetName(): string
    {
        return $this->streetName;
    }

    public function setStreetName(string $streetName): self
    {
        $this->streetName = $streetName;

        return $this;
    }

    public function getStreetType(): ?string
    {
        return $this->streetType;
    }

    public function setStreetType(?string $streetType): self
    {
        $this->streetType = $streetType;

        return $this;
    }

    public function getNormalizedName(): ?string
    {
        return $this->normalizedName;
    }

    public function setNormalizedName(?string $normalizedName): self
    {
        $this->normalizedName = $normalizedName;

        return $this;
    }
}
