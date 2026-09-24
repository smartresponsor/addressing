<?php

declare(strict_types=1);

namespace App\Addressing\Entity\Address;

use App\Objecting\EntityTrait\Embeddable\ObjectAuditEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectIdentityEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectStateEmbeddableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Addressing\Repository\AddressCountryEntityRepository::class)]
#[ORM\Table(name: 'address_country')]
#[ORM\Index(name: 'address_country_iso2_idx', columns: ['iso2'])]
class AddressCountryEntity
{
    use ObjectIdentityEmbeddableTrait;
    use ObjectAuditEmbeddableTrait;
    use ObjectStateEmbeddableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'iso2', type: 'string', length: 2)]
    private string $iso2 = 'US';

    #[ORM\Column(name: 'iso3', type: 'string', length: 3, nullable: true)]
    private ?string $iso3 = null;

    #[ORM\Column(name: 'numeric_code', type: 'string', length: 3, nullable: true)]
    private ?string $numericCode = null;

    #[ORM\Column(name: 'name_entity', type: 'string', length: 128)]
    private string $nameEntity = '';

    #[ORM\Column(name: 'native_name', type: 'string', length: 128, nullable: true)]
    private ?string $nativeName = null;

    #[ORM\Column(name: 'phone_code', type: 'string', length: 16, nullable: true)]
    private ?string $phoneCode = null;

    // Canonical `enabled` storage is owned by ObjectStateEmbeddableTrait.

    public function __construct(?string $objectUuid = null, ?string $objectSlug = null)
    {
        $this->initializeObjectIdentity($objectUuid, $objectSlug);
        $this->initializeObjectAudit();
        $this->initializeObjectState();
    }

    public function getIso2(): string
    {
        return $this->iso2;
    }

    public function setIso2(string $iso2): self
    {
        $this->iso2 = $iso2;

        return $this;
    }

    public function getIso3(): ?string
    {
        return $this->iso3;
    }

    public function setIso3(?string $iso3): self
    {
        $this->iso3 = $iso3;

        return $this;
    }

    public function getNumericCode(): ?string
    {
        return $this->numericCode;
    }

    public function setNumericCode(?string $numericCode): self
    {
        $this->numericCode = $numericCode;

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

    public function getNativeName(): ?string
    {
        return $this->nativeName;
    }

    public function setNativeName(?string $nativeName): self
    {
        $this->nativeName = $nativeName;

        return $this;
    }

    public function getPhoneCode(): ?string
    {
        return $this->phoneCode;
    }

    public function setPhoneCode(?string $phoneCode): self
    {
        $this->phoneCode = $phoneCode;

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
