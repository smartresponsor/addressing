<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\ServiceInterface\Address\Event;

interface AddressEventDispatcherInterface
{
    public function subscribe(string $eventName, callable $listener): void;

    public function dispatch(AddressEventInterface $event): void;
}
