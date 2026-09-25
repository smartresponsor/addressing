<?php

declare(strict_types=1);

namespace App\Addressing\Entity\Address;

use App\Objecting\EntityTrait\Embeddable\ObjectAuditEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectIdentityEmbeddableTrait;
use App\Objecting\EntityTrait\Embeddable\ObjectStateEmbeddableTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Addressing\Repository\AddressFormatEntityRepository::class)]
#[ORM\Table(name: 'address_format')]
#[ORM\Index(name: 'address_format_country_code_idx', columns: ['country_code', 'format_code'])]
class AddressFormatEntity
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

    #[ORM\Column(name: 'format_code', type: 'string', length: 32)]
    private string $formatCode = '';

    #[ORM\Column(name: 'display_pattern', type: 'string', length: 512)]
    private string $displayPattern = '';

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'required_fields', type: 'json')]
    private array $requiredFields = [];

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'allowed_fields', type: 'json')]
    private array $allowedFields = [];

    #[ORM\Column(name: 'postal_code_pattern', type: 'string', length: 128, nullable: true)]
    private ?string $postalCodePattern = null;

    #[ORM\Column(name: 'example', type: 'string', length: 512, nullable: true)]
    private ?string $example = null;

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

    public function getFormatCode(): string
    {
        return $this->formatCode;
    }

    public function setFormatCode(string $formatCode): self
    {
        $this->formatCode = $formatCode;

        return $this;
    }

    public function getDisplayPattern(): string
    {
        return $this->displayPattern;
    }

    public function setDisplayPattern(string $displayPattern): self
    {
        $this->displayPattern = $displayPattern;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getRequiredFields(): array
    {
        return $this->requiredFields;
    }

    /** @param array<string, mixed> $requiredFields */
    public function setRequiredFields(array $requiredFields): self
    {
        $this->requiredFields = $requiredFields;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getAllowedFields(): array
    {
        return $this->allowedFields;
    }

    /** @param array<string, mixed> $allowedFields */
    public function setAllowedFields(array $allowedFields): self
    {
        $this->allowedFields = $allowedFields;

        return $this;
    }

    public function getPostalCodePattern(): ?string
    {
        return $this->postalCodePattern;
    }

    public function setPostalCodePattern(?string $postalCodePattern): self
    {
        $this->postalCodePattern = $postalCodePattern;

        return $this;
    }

    public function getExample(): ?string
    {
        return $this->example;
    }

    public function setExample(?string $example): self
    {
        $this->example = $example;

        return $this;
    }
}
