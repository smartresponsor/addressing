<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Application\Event;

use App\ServiceInterface\Application\Event\AddressEventDispatcherServiceInterface;
use App\ServiceInterface\Application\Event\AddressEventInterface;
use Override;
use Throwable;

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

    #[Override]
    public function subscribe(string $eventName, callable $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    /**
     * {@inheritdoc}
     *
     * Absolute rule:
     * dispatcher must never throw or affect business flow.
     */
    #[Override]
    public function dispatch(AddressEventInterface $addressEvent): void
    {
        $name = $addressEvent->name();

        foreach ($this->listeners[$name] ?? [] as $listener) {
            try {
                $listener($addressEvent);
            } catch (Throwable) {
                // intentionally ignored:
                // dispatcher must never break the main flow
            }
        }
    }
}
