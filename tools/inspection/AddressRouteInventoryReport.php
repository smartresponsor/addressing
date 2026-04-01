<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$index = $root . '/public/index.php';
$routes = [];

if (is_file($index)) {
    $content = file_get_contents($index) ?: '';
    preg_match_all('/\$_SERVER\[\'REQUEST_METHOD\'\]\s*===\s*\'([A-Z]+)\'/m', $content, $methodMatches);
    preg_match_all('/\$_SERVER\[\'REQUEST_URI\'\].*?(\/[^\'\"]+)/m', $content, $uriMatches);
    $routes = [
        'method_tokens' => array_values(array_unique($methodMatches[1] ?? [])),
        'uri_tokens' => array_values(array_unique($uriMatches[1] ?? [])),
    ];
}

fwrite(STDOUT, json_encode([
    'component' => 'Addressing',
    'source' => 'public/index.php',
    'routes' => $routes,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
