<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Contract\Message;

use JsonSerializable;

final readonly class AddressValidationVerdict implements JsonSerializable
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
            $deliverableValue = $data['deliverable'];
            if (is_bool($deliverableValue)) {
                $deliverable = $deliverableValue;
            } elseif (is_int($deliverableValue) || is_float($deliverableValue)) {
                $deliverable = ((int) $deliverableValue) === 1;
            } elseif (is_string($deliverableValue)) {
                $normalizedValue = strtolower(trim($deliverableValue));
                if (in_array($normalizedValue, ['1', 'true', 'yes'], true)) {
                    $deliverable = true;
                } elseif (in_array($normalizedValue, ['0', 'false', 'no'], true)) {
                    $deliverable = false;
                }
            }
        }

        $granularity = null;
        if (array_key_exists('granularity', $data) && is_string($data['granularity'])) {
            $granularityValue = trim($data['granularity']);
            $granularity = '' === $granularityValue ? null : $granularityValue;
        }

        $quality = null;
        if (array_key_exists('quality', $data)) {
            $qualityValue = $data['quality'];
            if (is_int($qualityValue)) {
                $quality = $qualityValue;
            } elseif (is_float($qualityValue)) {
                $quality = (int) round($qualityValue);
            } elseif (is_string($qualityValue) && is_numeric($qualityValue)) {
                $quality = (int) round((float) $qualityValue);
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
