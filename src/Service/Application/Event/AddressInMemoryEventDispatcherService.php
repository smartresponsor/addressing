<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Service\Application\Event;

use App\Addressing\ServiceInterface\Application\Event\AddressEventDispatcherServiceInterface;
use App\Addressing\ServiceInterface\Application\Event\AddressEventInterface;

/**
 * In-memory event dispatcher.
 *
 * Absolute guarantees:
 * - dispatcher never breaks the main execution flow
 * - listener failures are fully isolated
 * - ordering is preserved
 * - no side effects outside this process
 */
final class AddressInMemoryEventDispatcherService implements AddressEventDispatcherServiceInterface
{
    /**
     * @var array<list<callable(AddressEventInterface): void>>
     */
    private array $listeners = [];

    #[\Override]
    public function subscribe(string $eventName, callable $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    /**
     * {}.
     *
     * Absolute rule:
     * dispatcher must never throw or affect business flow.
     */
    #[\Override]
    public function dispatch(AddressEventInterface $addressEvent): void
    {
        $nameEntity = $addressEvent->nameEntity();

        foreach ($this->listeners[$nameEntity] ?? [] as $listener) {
            try {
                $listener($addressEvent);
            } catch (\Throwable) {
                // intentionally ignored:
                // dispatcher must never break the main flow
            }
        }
    }
}
