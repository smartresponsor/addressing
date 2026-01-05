<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
namespace App\Domain\Shared\Event;

interface DispatcherInterface
{
    public function subscribe(string $eventName, callable $listener): void;
    public function dispatch(EventInterface $event): void;
}
