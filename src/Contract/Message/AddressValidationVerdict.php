<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Contract\Message;

final readonly class AddressValidationVerdict implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $signal
     */
    public function __construct(
        public ?bool $deliverable,
        public ?string $granularity,
        public ?int $quality,
        /** @var array<string, mixed> */
        public array $signal = [],
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): ?self
    {
        if (null === $data) {
            return null;
        }

        $deliverable = null;
        if (array_key_exists('deliverable', $data)) {
            $rawDeliverable = $data['deliverable'];
            if (is_bool($rawDeliverable)) {
                $deliverable = $rawDeliverable;
            } elseif (is_int($rawDeliverable) || is_float($rawDeliverable)) {
                $deliverable = ((int) $rawDeliverable) === 1;
            } elseif (is_string($rawDeliverable)) {
                $normalizedDeliverable = strtolower(trim($rawDeliverable));
                if (in_array($normalizedDeliverable, ['1', 'true', 'yes'], true)) {
                    $deliverable = true;
                } elseif (in_array($normalizedDeliverable, ['0', 'false', 'no'], true)) {
                    $deliverable = false;
                }
            }
        }

        $granularity = null;
        if (array_key_exists('granularity', $data) && is_string($data['granularity'])) {
            $normalizedGranularity = trim($data['granularity']);
            $granularity = '' === $normalizedGranularity ? null : $normalizedGranularity;
        }

        $quality = null;
        if (array_key_exists('quality', $data)) {
            $rawQuality = $data['quality'];
            if (is_int($rawQuality)) {
                $quality = $rawQuality;
            } elseif (is_float($rawQuality)) {
                $quality = (int) round($rawQuality);
            } elseif (is_string($rawQuality) && is_numeric($rawQuality)) {
                $quality = (int) round((float) $rawQuality);
            }
            if (null !== $quality) {
                $quality = max(0, min(100, $quality));
            }
        }

        $signal = [];
        if (array_key_exists('signal', $data) && is_array($data['signal'])) {
            /** @var array<string, mixed> $signal */
            $signal = $data['signal'];
        }

        return new self($deliverable, $granularity, $quality, $signal);
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'deliverable' => $this->deliverable,
            'granularity' => $this->granularity,
            'quality' => $this->quality,
            'signal' => $this->signal,
        ];
    }
}
