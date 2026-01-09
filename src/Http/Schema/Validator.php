<?php
/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
declare(strict_types=1);
namespace App\Http\Schema;

/**
 *
 */

/**
 *
 */
final class Validator
{
    /** @var array */
    private array $schemas = [
        'ValidateRequest' => [
            'required' => ['line1','city','region','postal','country'],
            'types' => ['line1'=>'string','line2'=>'string','city'=>'string','region'=>'string','postal'=>'string','country'=>'string']
        ],
        'ParseRequest' => [
            'required' => ['text'],
            'types' => ['text'=>'string','countryHint'=>'string']
        ],
    ];

    /**
     * @param string $schema
     * @param array $data
     * @return array|true[]
     */
    public function validate(string $schema, array $data): array
    {
        if (!isset($this->schemas[$schema])) return ['ok'=>false,'error'=>'unknown_schema'];
        $def = $this->schemas[$schema];
        foreach ($def['required'] as $k) {
            if (!array_key_exists($k, $data)) return ['ok'=>false,'error'=>'missing_' . $k];
        }
        foreach ($data as $k=>$v) {
            if (isset($def['types'][$k]) && $v !== null && gettype($v) !== $def['types'][$k]) {
                return ['ok'=>false,'error'=>'type_' . $k];
            }
        }
        return ['ok'=>true];
    }
}
