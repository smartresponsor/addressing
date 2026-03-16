<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Schema;

final class Validator
{
    /**
     * @var array<string, array{required: list<string>, types: array<string, 'string'|'int'|'float'|'bool'|'array'>}>
     */
    private array $schemas = [
        'ValidateRequest' => [
            'required' => ['line1', 'city', 'region', 'postal', 'country'],
            'types' => [
                'line1' => 'string',
                'line2' => 'string',
                'city' => 'string',
                'region' => 'string',
                'postal' => 'string',
                'country' => 'string',
            ],
        ],
        'ParseRequest' => [
            'required' => ['text'],
            'types' => ['text' => 'string', 'countryHint' => 'string'],
        ],
    ];

    /**
     * @param array<string, mixed> $data
     *
     * @return array{ok: true}|array{ok: false, error: non-empty-string}
     */
    public function validate(string $schema, array $data): array
    {
        $definition = $this->schemas[$schema] ?? null;
        if (null === $definition) {
            return ['ok' => false, 'error' => 'unknown_schema'];
        }

        foreach ($definition['required'] as $requiredKey) {
            if (!array_key_exists($requiredKey, $data) || null === $data[$requiredKey]) {
                return ['ok' => false, 'error' => 'missing_'.$requiredKey];
            }
        }

        foreach ($data as $key => $value) {
            $expectedType = $definition['types'][$key] ?? null;
            if (null === $expectedType || null === $value) {
                continue;
            }

            if (!$this->isExpectedType($value, $expectedType)) {
                return ['ok' => false, 'error' => 'type_'.$key];
            }
        }

        return ['ok' => true];
    }

    /** @param 'string'|'int'|'float'|'bool'|'array' $expectedType */
    private function isExpectedType(mixed $value, string $expectedType): bool
    {
        return match ($expectedType) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
        };
    }
}
