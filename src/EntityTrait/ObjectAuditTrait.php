<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\EntityTrait;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

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
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(name: 'deleted_by', type: 'integer', nullable: true)]
    private ?int $deletedBy = null;

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
    private ?\DateTimeImmutable $lastConfigUpdate = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'modified_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $modifiedAt;

    #[ORM\Column(name: 'last_request_date', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastRequestAt;

    #[ORM\Column(name: 'locked_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $lockedAt;

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
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['read', 'write'])]
    private ?array $ipRestriction = [];

    // region Lifecycle
    #[ORM\PrePersist]
    /**
     * Initializes audit timestamps on first persistence.
     */
    public function initializeTimestamps(): void
    {
        $timestamp = new \DateTimeImmutable();
        $this->slug ??= (string) Uuid::v4();
        $this->createdAt = $timestamp;
        $this->modifiedAt = $timestamp;
        $this->lastRequestAt = $timestamp;
        $this->lockedAt = $timestamp;
        $this->published = true;
    }

    #[ORM\PreUpdate]
    /**
     * Refreshes the modification timestamp.
     */
    public function updateTimestamps(): void
    {
        $this->modifiedAt = new \DateTimeImmutable();
    }

    /**
     * Returns the stored configuration payload.
     */
    public function getConfig(bool $decrypted = true): ?array
    {
        return ($this->configEncrypted && $decrypted)
            ? $this->decryptedConfig
            : $this->config;
    }

    /**
     * Stores the configuration payload and refreshes its timestamp.
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
        $this->decryptedConfig = $config;
        $this->lastConfigUpdate = new \DateTimeImmutable();
    }

    /**
     * Indicates whether the stored configuration is encrypted.
     */
    public function isConfigEncrypted(): bool
    {
        return $this->configEncrypted;
    }

    /**
     * Marks the configuration as encrypted or plain.
     */
    public function setConfigEncrypted(bool $flag): void
    {
        $this->configEncrypted = $flag;
    }

    /**
     * Returns the expiration timestamp.
     */
    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * Sets the expiration timestamp.
     */
    public function setExpiresAt(\DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    /**
     * Returns the entity token.
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Sets the entity token.
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * Returns the configured IP allow list.
     */
    public function getIpRestriction(): array
    {
        return $this->ipRestriction ?? [];
    }

    /**
     * Replaces the configured IP allow list.
     */
    public function setIpRestriction(array $ips): void
    {
        $this->ipRestriction = $ips;
    }

    /**
     * Checks whether the provided IP is allowed.
     */
    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->ipRestriction)) {
            return true;
        }

        return in_array($ip, $this->ipRestriction, true);
    }

    /**
     * Marks the entity as softly deleted.
     */
    public function softDelete(): void
    {
        $this->isDeleted = true;
        $this->deletedAt = new \DateTimeImmutable();
    }

    /**
     * Returns the identifier.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Sets the identifier.
     */
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    /**
     * Reports whether the entity is published.
     */
    public function isPublished(): bool
    {
        return $this->published;
    }

    /**
     * Sets the publication flag.
     */
    public function setPublished(bool $published): void
    {
        $this->published = $published;
    }

    /**
     * Reports whether the entity is deleted.
     */
    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    /**
     * Sets the deletion flag.
     */
    public function setIsDeleted(bool $isDeleted): void
    {
        $this->isDeleted = $isDeleted;
    }

    /**
     * Returns the deletion timestamp.
     */
    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * Sets the deletion timestamp.
     */
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * Returns the deletion owner identifier.
     */
    public function getDeletedBy(): ?int
    {
        return $this->deletedBy;
    }

    /**
     * Sets the deletion owner identifier.
     */
    public function setDeletedBy(?int $deletedBy): void
    {
        $this->deletedBy = $deletedBy;
    }

    /**
     * Returns the slug.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Sets the slug.
     */
    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    /**
     * Returns the code.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Sets the code.
     */
    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    /**
     * Returns the creation timestamp.
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Sets the creation timestamp.
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * Returns the modification timestamp.
     */
    public function getModifiedAt(): \DateTimeImmutable
    {
        return $this->modifiedAt;
    }

    /**
     * Sets the modification timestamp.
     */
    public function setModifiedAt(\DateTimeImmutable $modifiedAt): void
    {
        $this->modifiedAt = $modifiedAt;
    }

    /**
     * Returns the lock timestamp.
     */
    public function getLockedAt(): \DateTimeImmutable
    {
        return $this->lockedAt;
    }

    /**
     * Sets the lock timestamp.
     */
    public function setLockedAt(\DateTimeImmutable $lockedAt): void
    {
        $this->lockedAt = $lockedAt;
    }

    /**
     * Returns the creator identifier.
     */
    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    /**
     * Sets the creator identifier.
     */
    public function setCreatedBy(int $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    /**
     * Returns the modifier identifier.
     */
    public function getModifiedBy(): int
    {
        return $this->modifiedBy;
    }

    /**
     * Sets the modifier identifier.
     */
    public function setModifiedBy(int $modifiedBy): void
    {
        $this->modifiedBy = $modifiedBy;
    }

    /**
     * Returns the locker identifier.
     */
    public function getLockedBy(): int
    {
        return $this->lockedBy;
    }

    /**
     * Sets the locker identifier.
     */
    public function setLockedBy(int $lockedBy): void
    {
        $this->lockedBy = $lockedBy;
    }
}
