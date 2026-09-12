<?php

declare(strict_types=1);

namespace App\Addressing\Entity\Address;

use App\Objecting\EntityTrait\Embeddable\ObjectAuditEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectIdentityEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectStateEmbeddableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Addressing\Repository\Address\AddressComponentEntityRepository::class)]
#[ORM\Table(name: 'address_component')]
#[ORM\Index(name: 'address_component_address_type_idx', columns: ['address_id', 'component_type'])]
class AddressComponentEntity
{
    use ObjectIdentityEmbeddableTrait;
    use ObjectAuditEmbeddableTrait;
    use ObjectStateEmbeddableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'address_id', type: 'string', length: 26, nullable: true)]
    private ?string $addressId = null;

    #[ORM\Column(name: 'component_type', type: 'string', length: 64)]
    private string $componentType = '';

    #[ORM\Column(name: 'component_value', type: 'string', length: 256)]
    private string $componentValue = '';

    #[ORM\Column(name: 'normalized_value', type: 'string', length: 256, nullable: true)]
    private ?string $normalizedValue = null;

    #[ORM\Column(name: 'source', type: 'string', length: 64, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(name: 'confidence', type: 'float', nullable: true)]
    private ?float $confidence = null;

    public function __construct(?string $objectUuid = null, ?string $objectSlug = null)
    {
        $this->initializeObjectIdentity($objectUuid, $objectSlug);
        $this->initializeObjectAudit();
        $this->initializeObjectState();
    }

    public function getAddressId(): ?string
    {
        return $this->addressId;
    }

    public function setAddressId(?string $addressId): self
    {
        $this->addressId = $addressId;

        return $this;
    }

    public function getComponentType(): string
    {
        return $this->componentType;
    }

    public function setComponentType(string $componentType): self
    {
        $this->componentType = $componentType;

        return $this;
    }

    public function getComponentValue(): string
    {
        return $this->componentValue;
    }

    public function setComponentValue(string $componentValue): self
    {
        $this->componentValue = $componentValue;

        return $this;
    }

    public function getNormalizedValue(): ?string
    {
        return $this->normalizedValue;
    }

    public function setNormalizedValue(?string $normalizedValue): self
    {
        $this->normalizedValue = $normalizedValue;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;

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
}
