<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\Service\Address\Event;

use App\ServiceInterface\Address\Event\AddressEventDispatcherInterface;
use App\ServiceInterface\Address\Event\AddressEventInterface;

final class InMemoryAddressEventDispatcher implements AddressEventDispatcherInterface
{
    /** @var array<string, list<callable>> */
    private array $listener = [];

    public function subscribe(string $eventName, callable $listener): void
    {
        $this->listener[$eventName] ??= [];
        $this->listener[$eventName][] = $listener;
    }

    public function dispatch(AddressEventInterface $event): void
    {
        $name = $event->name();
        foreach ($this->listener[$name] ?? [] as $listener) {
            $listener($event);
        }
    }
}
