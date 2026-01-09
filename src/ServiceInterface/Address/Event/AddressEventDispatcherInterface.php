<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);

namespace App\ServiceInterface\Address\Event;

/**
 *
 */

/**
 *
 */
interface AddressEventDispatcherInterface
{
    /**
     * @param string $eventName
     * @param callable $listener
     * @return void
     */
    public function subscribe(string $eventName, callable $listener): void;

    /**
     * @param \App\ServiceInterface\Address\Event\AddressEventInterface $event
     * @return void
     */
    public function dispatch(AddressEventInterface $event): void;
}
