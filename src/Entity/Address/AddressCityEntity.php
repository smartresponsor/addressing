<?php

declare(strict_types=1);

namespace App\Addressing\Entity\Address;

use App\Objecting\EntityTrait\Embeddable\ObjectAuditEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectIdentityEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectStateEmbeddableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Addressing\Repository\Address\AddressCityEntityRepository::class)]
#[ORM\Table(name: 'address_city')]
#[ORM\Index(name: 'address_city_country_name_idx', columns: ['country_code', 'nameEntity'])]
class AddressCityEntity
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

    #[ORM\Column(name: 'nameEntity', type: 'string', length: 128)]
    private string $nameEntity = '';

    #[ORM\Column(name: 'normalized_name', type: 'string', length: 128, nullable: true)]
    private ?string $normalizedName = null;

    #[ORM\Column(name: 'timezone', type: 'string', length: 64, nullable: true)]
    private ?string $timezone = null;

    // Canonical `enabled` storage is owned by ObjectStateEmbeddableTrait.

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

    public function getName(): string
    {
        return $this->nameEntity;
    }

    public function setName(string $nameEntity): self
    {
        $this->nameEntity = $nameEntity;

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

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->isObjectEnabled();
    }

    public function setEnabled(bool $enabled): self
    {
        $this->setObjectEnabled($enabled);

        return $this;
    }
}
