<?php

declare(strict_types=1);

/**
 * @return array{method_tokens: list<string>, uri_tokens: list<string>, uri_patterns: list<string>}
 */
function addressRouteInventory(string $content): array
{
    preg_match_all("/'([A-Z]+)'\\s*===\\s*\\\$method/", $content, $methodMatches);
    preg_match_all("/'((?:\\/address|\\/api\\/address)[^']*)'\\s*===\\s*\\\$pathInfo/", $content, $uriMatches);
    preg_match_all("/preg_match\\('([^']+)'\\s*,\\s*\\\$pathInfo/", $content, $patternMatches);

    $methods = array_values(array_unique($methodMatches[1] ?? []));
    $uris = array_values(array_unique($uriMatches[1] ?? []));
    $patterns = array_values(array_unique($patternMatches[1] ?? []));

    sort($methods);
    sort($uris);
    sort($patterns);

    return [
        'method_tokens' => $methods,
        'uri_tokens' => $uris,
        'uri_patterns' => $patterns,
    ];
}

$root = dirname(__DIR__, 2);
$index = $root.'/public/index.php';
$content = is_file($index) ? (file_get_contents($index) ?: '') : '';

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    fwrite(STDOUT, json_encode([
        'component' => 'Addressing',
        'source' => 'public/index.php',
        'routes' => addressRouteInventory($content),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
}
