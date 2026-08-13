<?php

declare(strict_types=1);

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => 'reference',
    'layers' => [
        'HttpController' => '^App\Addressing\\Http\\Controller\\.*',
        'Http' => '^App\Addressing\\Http\\(?!Controller\\).*',
        'ServiceInterface' => '^App\Addressing\\ServiceInterface\\.*',
        'Service' => '^App\Addressing\\Service\\.*',
        'RepositoryInterface' => '^App\Addressing\\RepositoryInterface\\.*',
        'Repository' => '^App\Addressing\\Repository\\.*',
        'Integration' => '^App\Addressing\\Integration\\.*',
        'Contract' => '^App\Addressing\\Contract\\.*',
        'EntityInterface' => '^App\Addressing\\EntityInterface\\.*',
        'Entity' => '^App\Addressing\\Entity\\.*',
        'Value' => '^App\Addressing\\Value\\.*',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
