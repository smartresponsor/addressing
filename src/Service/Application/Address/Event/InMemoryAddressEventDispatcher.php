<?php

/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\Service\Application\Address\Event;

use App\ServiceInterface\Application\Address\Event\AddressEventDispatcherInterface;
use App\ServiceInterface\Application\Address\Event\AddressEventInterface;

/**
 * In-memory event dispatcher.
 *
 * Absolute guarantees:
 * - dispatcher never breaks the main execution flow
 * - listener failures are fully isolated
 * - ordering is preserved
 * - no side effects outside this process
 */
final class InMemoryAddressEventDispatcher implements AddressEventDispatcherInterface
{
    /**
     * @var array<list<callable(AddressEventInterface): void>>
     */
    private array $listener = [];

    public function subscribe(string $eventName, callable $listener): void
    {
        $this->listener[$eventName][] = $listener;
    }

    /**
     * {@inheritdoc}
     *
     * Absolute rule:
     * dispatcher must never throw or affect business flow.
     */
    public function dispatch(AddressEventInterface $event): void
    {
        $name = $event->name();

        foreach ($this->listener[$name] ?? [] as $listener) {
            try {
                $listener($event);
            } catch (\Throwable) {
                // intentionally ignored:
                // dispatcher must never break the main flow
            }
        }
    }
}
