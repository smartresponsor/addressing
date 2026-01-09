<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\Service\Address\Event;

use App\ServiceInterface\Address\Event\AddressEventDispatcherInterface;
use App\ServiceInterface\Address\Event\AddressEventInterface;
use Throwable;

/**
 *
 */
final class InMemoryAddressEventDispatcher implements AddressEventDispatcherInterface
{
    /**
     * @var array<string, list<callable>>
     */
    private array $listeners = [];

    /**
     * @param string $eventName
     * @param callable $listener
     * @return void
     */
    public function subscribe(string $eventName, callable $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    /**
     * @param \App\ServiceInterface\Address\Event\AddressEventInterface $event
     * @return void
     */
    public function dispatch(AddressEventInterface $event): void
    {
        $name = $event->name();

        if (empty($this->listeners[$name])) {
            return;
        }

        $listeners = $this->listeners[$name];

        foreach ($listeners as $listener) {
            try {
                $listener($event);
            } catch (Throwable $e) {
                // Listener failures are intentionally isolated.

                // Extension point:
                // - logger
                // - metrics
            }
        }
    }
}
