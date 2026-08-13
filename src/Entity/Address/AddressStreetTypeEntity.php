<?php

declare(strict_types=1);

namespace App\Addressing\Entity\Address;

use App\Objecting\EntityTrait\Embeddable\ObjectAuditEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectIdentityEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectStateEmbeddableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Addressing\Repository\Address\AddressStreetTypeEntityRepository::class)]
#[ORM\Table(name: 'address_street_type')]
#[ORM\Index(name: 'address_street_type_country_code_idx', columns: ['country_code', 'code'])]
class AddressStreetTypeEntity
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

    #[ORM\Column(name: 'label', type: 'string', length: 64)]
    private string $label = '';

    #[ORM\Column(name: 'abbreviation', type: 'string', length: 16, nullable: true)]
    private ?string $abbreviation = null;

    #[ORM\Column(name: 'sort_order', type: 'integer')]
    private int $sortOrder = 0;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getAbbreviation(): ?string
    {
        return $this->abbreviation;
    }

    public function setAbbreviation(?string $abbreviation): self
    {
        $this->abbreviation = $abbreviation;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }
}
