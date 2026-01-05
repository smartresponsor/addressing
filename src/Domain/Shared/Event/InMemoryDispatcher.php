<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
namespace App\Domain\Shared\Event;

final class InMemoryDispatcher implements DispatcherInterface
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function subscribe(string $eventName, callable $listener): void
    {
        $this->listeners[$eventName] ??= [];
        $this->listeners[$eventName][] = $listener;
    }

    public function dispatch(EventInterface $event): void
    {
        $name = $event->name();
        foreach ($this->listeners[$name] ?? [] as $listener) {
            $listener($event);
        }
    }
}
