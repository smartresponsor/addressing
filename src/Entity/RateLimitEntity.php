<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rate_limit')]
class RateLimitEntity
{
    #[ORM\Id]
    #[ORM\Column(name: 'client', type: 'string', length: 255)]
    private string $client;

    #[ORM\Id]
    #[ORM\Column(name: 'rkey', type: 'string', length: 255)]
    private string $rkey;

    #[ORM\Column(name: 'ts', type: 'integer')]
    private int $ts;

    #[ORM\Column(name: 'cnt', type: 'integer')]
    private int $cnt;

    public function getClient(): string
    {
        return $this->client;
    }

    public function setClient(string $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getRkey(): string
    {
        return $this->rkey;
    }

    public function setRkey(string $rkey): self
    {
        $this->rkey = $rkey;

        return $this;
    }

    public function getTs(): int
    {
        return $this->ts;
    }

    public function setTs(int $ts): self
    {
        $this->ts = $ts;

        return $this;
    }

    public function getCnt(): int
    {
        return $this->cnt;
    }

    public function setCnt(int $cnt): self
    {
        $this->cnt = $cnt;

        return $this;
    }
}
