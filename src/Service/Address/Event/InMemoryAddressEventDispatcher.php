<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\Service\Address\Event;

use App\ServiceInterface\Address\Event\AddressEventDispatcherInterface;
use App\ServiceInterface\Address\Event\AddressEventInterface;

/**
 *
 */

/**
 *
 */
final class InMemoryAddressEventDispatcher implements AddressEventDispatcherInterface
{
    /** @var array */
    private array $listener = [];

    /**
     * @param string $eventName
     * @param callable $listener
     * @return void
     */
    public function subscribe(string $eventName, callable $listener): void
    {
        $this->listener[$eventName] ??= [];
        $this->listener[$eventName][] = $listener;
    }

    /**
     * @param \App\ServiceInterface\Address\Event\AddressEventInterface $event
     * @return void
     */
    public function dispatch(AddressEventInterface $event): void
    {
        $name = $event->name();
        foreach ($this->listener[$name] ?? [] as $listener) {
            $listener($event);
        }
    }
}
