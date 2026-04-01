<?php

declare(strict_types=1);

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'status' => 'reference',
    'layers' => [
        'HttpController' => '^App\\Http\\Controller\\.*',
        'Http' => '^App\\Http\\(?!Controller\\).*',
        'ServiceInterface' => '^App\\ServiceInterface\\.*',
        'Service' => '^App\\Service\\.*',
        'RepositoryInterface' => '^App\\RepositoryInterface\\.*',
        'Repository' => '^App\\Repository\\.*',
        'Integration' => '^App\\Integration\\.*',
        'Contract' => '^App\\Contract\\.*',
        'EntityInterface' => '^App\\EntityInterface\\.*',
        'Entity' => '^App\\Entity\\.*',
        'Value' => '^App\\Value\\.*',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
