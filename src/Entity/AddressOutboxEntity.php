<?php

declare(strict_types=1);

namespace App\Addressing\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'address_outbox')]
#[ORM\Index(name: 'address_outbox_stream_created_idx', columns: ['stream', 'created_at'])]
#[ORM\Index(name: 'address_outbox_publish_idx', columns: ['published_at', 'locked_at', 'id'])]
/**
 * Persists an Addressing integration event until a publisher claims and delivers it.
 */
class AddressOutboxEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'stream', type: 'string', length: 32, options: ['default' => 'address'])]
    private string $stream = 'address';

    #[ORM\Column(name: 'event_name', type: 'string', length: 128)]
    private string $eventName;

    #[ORM\Column(name: 'event_version', type: 'integer')]
    private int $eventVersion;

    #[ORM\Column(name: 'payload', type: 'text')]
    private string $payload;

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'published_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(name: 'locked_at', type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $lockedAt = null;

    #[ORM\Column(name: 'locked_by', type: 'string', length: 64, nullable: true)]
    private ?string $lockedBy = null;

    #[ORM\Column(name: 'published_attempt', type: 'integer', options: ['default' => 0])]
    private int $publishedAttempt = 0;

    #[ORM\Column(name: 'last_error', type: 'text', nullable: true)]
    private ?string $lastError = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStream(): string
    {
        return $this->stream;
    }

    public function setStream(string $stream): self
    {
        $this->stream = $stream;

        return $this;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function setEventName(string $eventName): self
    {
        $this->eventName = $eventName;

        return $this;
    }

    public function getEventVersion(): int
    {
        return $this->eventVersion;
    }

    public function setEventVersion(int $eventVersion): self
    {
        $this->eventVersion = $eventVersion;

        return $this;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Returns the canonical creation instant used to order unpublished outbox records.
     */
    public function createdAt(): \DateTimeImmutable
    {
        return $this->getCreatedAt();
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getLockedAt(): ?\DateTimeImmutable
    {
        return $this->lockedAt;
    }

    public function setLockedAt(?\DateTimeImmutable $lockedAt): self
    {
        $this->lockedAt = $lockedAt;

        return $this;
    }

    /**
     * Returns the instant at which the current publisher claim was acquired.
     */
    public function lockedAt(): ?\DateTimeImmutable
    {
        return $this->getLockedAt();
    }

    public function getLockedBy(): ?string
    {
        return $this->lockedBy;
    }

    public function setLockedBy(?string $lockedBy): self
    {
        $this->lockedBy = $lockedBy;

        return $this;
    }

    /**
     * Returns the opaque publisher identity that currently owns the delivery claim.
     */
    public function lockedBy(): ?string
    {
        return $this->getLockedBy();
    }

    public function getPublishedAttempt(): int
    {
        return $this->publishedAttempt;
    }

    public function setPublishedAttempt(int $publishedAttempt): self
    {
        $this->publishedAttempt = $publishedAttempt;

        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): self
    {
        $this->lastError = $lastError;

        return $this;
    }

    /**
     * Claims this record for publication and records the claiming publisher identity.
     */
    public function lock(?string $lockedBy = null, ?\DateTimeImmutable $lockedAt = null): self
    {
        $this->lockedBy = $lockedBy;
        $this->lockedAt = $lockedAt ?? new \DateTimeImmutable();

        return $this;
    }

    /**
     * Releases the publication claim so the outbox record may be retried safely.
     */
    public function unlock(): self
    {
        $this->lockedBy = null;
        $this->lockedAt = null;

        return $this;
    }
}
