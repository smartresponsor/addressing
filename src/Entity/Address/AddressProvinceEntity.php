<?php

declare(strict_types=1);

namespace App\Addressing\Entity\Address;

use App\Objecting\EntityTrait\Embeddable\ObjectAuditEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectIdentityEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectStateEmbeddableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Addressing\Repository\AddressProvinceEntityRepository::class)]
#[ORM\Table(name: 'address_province')]
#[ORM\Index(name: 'address_province_country_code_idx', columns: ['country_code', 'code'])]
class AddressProvinceEntity
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

    #[ORM\Column(name: 'code', type: 'string', length: 32)]
    private string $code = '';

    #[ORM\Column(name: 'nameEntity', type: 'string', length: 128)]
    private string $nameEntity = '';

    #[ORM\Column(name: 'type', type: 'string', length: 32, nullable: true)]
    private ?string $type = null;

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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

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
