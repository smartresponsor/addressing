<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\ServiceInterface\Application\Event;

use App\Addressing\EventInterface\AddressEventInterface;

/**
 * Defines subscription and dispatch operations for Addressing application events.
 */
interface AddressEventDispatcherServiceInterface
{
    /** Registers a listener for one named Addressing application event. */
    public function subscribe(string $eventName, callable $listener): void;

    /** Dispatches one Addressing event to its registered application listeners. */
    public function dispatch(AddressEventInterface $addressEvent): void;
}
