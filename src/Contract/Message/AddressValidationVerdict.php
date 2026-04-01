<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
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
            $v = $data['deliverable'];
            if (is_bool($v)) {
                $deliverable = $v;
            } elseif (is_int($v) || is_float($v)) {
                $deliverable = ((int) $v) === 1;
            } elseif (is_string($v)) {
                $vv = strtolower(trim($v));
                if (in_array($vv, ['1', 'true', 'yes'], true)) {
                    $deliverable = true;
                } elseif (in_array($vv, ['0', 'false', 'no'], true)) {
                    $deliverable = false;
                }
            }
        }

        $granularity = null;
        if (array_key_exists('granularity', $data) && is_string($data['granularity'])) {
            $g = trim($data['granularity']);
            $granularity = '' === $g ? null : $g;
        }

        $quality = null;
        if (array_key_exists('quality', $data)) {
            $q = $data['quality'];
            if (is_int($q)) {
                $quality = $q;
            } elseif (is_float($q)) {
                $quality = (int) round($q);
            } elseif (is_string($q) && is_numeric($q)) {
                $quality = (int) round((float) $q);
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
