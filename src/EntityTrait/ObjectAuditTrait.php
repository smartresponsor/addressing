<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);


namespace App\EntityTrait;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 *
 */

/**
 *
 */
trait ObjectAuditTrait
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\Column(name: 'published', type: 'boolean', options: ['default' => true])]
    #[Groups(['read', 'write'])]
    private bool $published = true;

    #[ORM\Column(type: 'boolean')]
    private bool $isDeleted = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deletedBy = null;

    #[ORM\Column(name: 'slug', type: 'string', unique: true)]
    private string $slug;

    #[ORM\Column(name: 'code', type: 'string', unique: true)]
    private string $code;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Groups(['read', 'write'])]
    private string $token;

    #[ORM\Column(type: 'json', nullable: true)]
    protected ?array $config = [];

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $configEncrypted = false;

    protected array $decryptedConfig = [];

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastConfigUpdate = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'modified_at', type: 'datetime_immutable')]
    private DateTimeImmutable $modifiedAt;

    #[ORM\Column(name: 'last_request_date', type: 'datetime_immutable')]
    private DateTimeImmutable $lastRequestAt;

    #[ORM\Column(name: 'locked_at', type: 'datetime_immutable')]
    private DateTimeImmutable $lockedAt;

    #[ORM\Column(name: 'created_by', type: 'integer', options: ['default' => 1])]
    private int $createdBy = 1;

    #[ORM\Column(name: 'modified_by', type: 'integer', options: ['default' => 1])]
    private int $modifiedBy = 1;

    #[Groups(['read', 'write'])]
    #[ORM\Column(name: 'locked_by', type: 'integer', options: ['default' => 1])]
    private int $lockedBy = 1;

    #[ORM\Column(name: 'work_flow', type: 'string', options: ['default' => 'submitted'])]
    private string $workFlow = 'submitted';

    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    protected int $version = 1;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['read', 'write'])]
    private ?DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?array $ipRestriction = [];

    #region Lifecycle
    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $t = new DateTimeImmutable();
        $this->slug ??= (string)Uuid::v4();
        $this->createdAt = $t;
        $this->modifiedAt = $t;
        $this->lastRequestAt = $t;
        $this->lockedAt = $t;
        $this->published = true;
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->modifiedAt = new DateTimeImmutable();
    }

    public function getConfig(bool $decrypted = true): ?array
    {
        return ($this->configEncrypted && $decrypted)
            ? $this->decryptedConfig
            : $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
        $this->decryptedConfig = $config;
        $this->lastConfigUpdate = new DateTimeImmutable();
    }

    public function isConfigEncrypted(): bool
    {
        return $this->configEncrypted;
    }

    public function setConfigEncrypted(bool $flag): void
    {
        $this->configEncrypted = $flag;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    #
    public function getIpRestriction(): array
    {
        return $this->ipRestriction ?? [];
    }

    public function setIpRestriction(array $ips): void
    {
        $this->ipRestriction = $ips;
    }

    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->ipRestriction)) {
            return true;
        }
        return in_array($ip, $this->ipRestriction, true);
    }

    public function softDelete(): void
    {
        $this->isDeleted = true;
        $this->deletedAt = new DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): void
    {
        $this->published = $published;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): void
    {
        $this->isDeleted = $isDeleted;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    public function getDeletedBy(): ?DateTimeImmutable
    {
        return $this->deletedBy;
    }

    public function setDeletedBy(?DateTimeImmutable $deletedBy): void
    {
        $this->deletedBy = $deletedBy;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getModifiedAt(): DateTimeImmutable
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(DateTimeImmutable $modifiedAt): void
    {
        $this->modifiedAt = $modifiedAt;
    }

    public function getLockedAt(): DateTimeImmutable
    {
        return $this->lockedAt;
    }

    public function setLockedAt(DateTimeImmutable $lockedAt): void
    {
        $this->lockedAt = $lockedAt;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getModifiedBy(): int
    {
        return $this->modifiedBy;
    }

    public function setModifiedBy(int $modifiedBy): void
    {
        $this->modifiedBy = $modifiedBy;
    }

    public function getLockedBy(): int
    {
        return $this->lockedBy;
    }

    public function setLockedBy(int $lockedBy): void
    {
        $this->lockedBy = $lockedBy;
    }


}
