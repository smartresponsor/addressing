<?php
declare(strict_types=1);
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */


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
    #[Groups(['read','write'])]
    private bool $published = true;

    #[ORM\Column(type:'boolean')]
    private bool $isDeleted=false;

    #[ORM\Column(type:'datetime_immutable', nullable:true)]
    private ?DateTimeImmutable $deletedAt=null;

    #[ORM\Column(type:'datetime_immutable', nullable:true)]
    private ?DateTimeImmutable $deletedBy=null;

    #[ORM\Column(name: 'slug', type: 'string', unique: true)]
    private string $slug;

    #[ORM\Column(name: 'code', type: 'string', unique: true)]
    private string $code;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Groups(['read','write'])]
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
    #[Groups(['read','write'])]
    private ?DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['read','write'])]
    private ?array $ipRestriction = [];

    #region Lifecycle

    /**
     * @return void
     */
    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $t = new DateTimeImmutable();
        $this->slug = $this->slug ?? (string)Uuid::v4();
        $this->createdAt = $t;
        $this->modifiedAt = $t;
        $this->lastRequestAt = $t;
        $this->lockedAt = $t;
        $this->published = true;
    }

    /**
     * @return void
     */
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->modifiedAt = new DateTimeImmutable();
    }

    /**
     * @param bool $decrypted
     * @return array|null
     */
    public function getConfig(bool $decrypted = true): ?array
    {
        return ($this->configEncrypted && $decrypted)
            ? $this->decryptedConfig
            : $this->config;
    }

    /**
     * @param array $config
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
        $this->decryptedConfig = $config;
        $this->lastConfigUpdate = new DateTimeImmutable();
    }

    /**
     * @return bool
     */
    public function isConfigEncrypted(): bool
    {
        return $this->configEncrypted;
    }

    /**
     * @param bool $flag
     * @return void
     */
    public function setConfigEncrypted(bool $flag): void
    {
        $this->configEncrypted = $flag;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * @param \DateTimeImmutable $expiresAt
     * @return void
     */
    public function setExpiresAt(DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $token
     * @return void
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    #

    /**
     * @return array
     */
    public function getIpRestriction(): array
    {
        return $this->ipRestriction ?? [];
    }

    /**
     * @param array $ips
     * @return void
     */
    public function setIpRestriction(array $ips): void
    {
        $this->ipRestriction = $ips;
    }

    /**
     * @param string $ip
     * @return bool
     */
    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->ipRestriction)) {
            return true;
        }
        return in_array($ip, $this->ipRestriction, true);
    }

    /**
     * @return void
     */
    public function softDelete(): void { $this->isDeleted=true; $this->deletedAt=new DateTimeImmutable(); }

    /**
     * @return void
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void { $this->createdAt=new DateTimeImmutable(); }

    /**
     * @return void
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt=new DateTimeImmutable(); }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     */
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return bool
     */
    public function isPublished(): bool
    {
        return $this->published;
    }

    /**
     * @param bool $published
     */
    public function setPublished(bool $published): void
    {
        $this->published = $published;
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    /**
     * @param bool $isDeleted
     */
    public function setIsDeleted(bool $isDeleted): void
    {
        $this->isDeleted = $isDeleted;
    }

    /**
     * @return \DateTimeImmutable|null
     */
    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * @param \DateTimeImmutable|null $deletedAt
     */
    public function setDeletedAt(?DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * @return \DateTimeImmutable|null
     */
    public function getDeletedBy(): ?DateTimeImmutable
    {
        return $this->deletedBy;
    }

    /**
     * @param \DateTimeImmutable|null $deletedBy
     */
    public function setDeletedBy(?DateTimeImmutable $deletedBy): void
    {
        $this->deletedBy = $deletedBy;
    }

    /**
     * @return string
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @param string $slug
     */
    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTimeImmutable $createdAt
     */
    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getModifiedAt(): DateTimeImmutable
    {
        return $this->modifiedAt;
    }

    /**
     * @param \DateTimeImmutable $modifiedAt
     */
    public function setModifiedAt(DateTimeImmutable $modifiedAt): void
    {
        $this->modifiedAt = $modifiedAt;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getLockedAt(): DateTimeImmutable
    {
        return $this->lockedAt;
    }

    /**
     * @param \DateTimeImmutable $lockedAt
     */
    public function setLockedAt(DateTimeImmutable $lockedAt): void
    {
        $this->lockedAt = $lockedAt;
    }

    /**
     * @return int
     */
    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    /**
     * @param int $createdBy
     */
    public function setCreatedBy(int $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    /**
     * @return int
     */
    public function getModifiedBy(): int
    {
        return $this->modifiedBy;
    }

    /**
     * @param int $modifiedBy
     */
    public function setModifiedBy(int $modifiedBy): void
    {
        $this->modifiedBy = $modifiedBy;
    }

    /**
     * @return int
     */
    public function getLockedBy(): int
    {
        return $this->lockedBy;
    }

    /**
     * @param int $lockedBy
     */
    public function setLockedBy(int $lockedBy): void
    {
        $this->lockedBy = $lockedBy;
    }



}
