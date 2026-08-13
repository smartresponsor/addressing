<?php

declare(strict_types=1);

namespace App\Addressing\Lifecycle;

/**
 * Lifecycle guard for address.
 *
 * The policy is intentionally framework-free: entities/services can call it
 * without introducing cross-component Doctrine dependencies.
 */
final class AddressLifecyclePolicy
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['candidate', 'validated', 'rejected'],
        'candidate' => ['validated', 'rejected', 'superseded'],
        'validated' => ['primary', 'deprecated', 'superseded'],
        'primary' => ['validated', 'deprecated', 'superseded'],
        'rejected' => ['candidate'],
        'deprecated' => ['superseded', 'archived'],
        'superseded' => ['archived'],
        'archived' => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        $from = self::normalize($from);
        $to = self::normalize($to);

        if ($from === $to) {
            return true;
        }

        return \in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function assertCanTransition(string $from, string $to): void
    {
        if (!$this->canTransition($from, $to)) {
            throw new \DomainException(sprintf('Invalid address lifecycle transition from "%s" to "%s".', $from, $to));
        }
    }

    /** @return list<string> */
    public function allowedNextStatuses(string $from): array
    {
        return self::TRANSITIONS[self::normalize($from)] ?? [];
    }

    private static function normalize(string $status): string
    {
        return strtolower(trim($status));
    }
}
