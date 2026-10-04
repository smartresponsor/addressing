<?php

declare(strict_types=1);

/**
 * Extracts the deterministic Symfony route inventory from Addressing route attributes.
 *
 * @return array{method_tokens: list<string>, uri_tokens: list<string>, uri_patterns: list<string>}
 */
function addressRouteInventory(string $content): array
{
    preg_match_all('/#\\[Route\\((.*?)\\)\\]/', $content, $routeMatches);

    $methods = [];
    $uris = [];
    $patterns = [];

    foreach ($routeMatches[1] ?? [] as $arguments) {
        if (1 !== preg_match("/^'([^']+)'/", $arguments, $pathMatch)) {
            continue;
        }

        $path = $pathMatch[1];
        $uris[] = $path;
        if (str_contains($path, '{')) {
            $patterns[] = $path;
        }

        if (1 !== preg_match('/methods:\\s*\\[([^\\]]+)\\]/', $arguments, $methodMatch)) {
            continue;
        }

        preg_match_all("/'([A-Z]+)'/", $methodMatch[1], $methodTokens);
        foreach ($methodTokens[1] ?? [] as $method) {
            $methods[] = $method;
        }
    }

    $methods = array_values(array_unique($methods));
    $uris = array_values(array_unique($uris));
    $patterns = array_values(array_unique($patterns));

    sort($methods);
    sort($uris);
    sort($patterns);

    return [
        'method_tokens' => $methods,
        'uri_tokens' => $uris,
        'uri_patterns' => $patterns,
    ];
}

/** @return array{paths: list<string>, content: string} */
function addressControllerRouteSources(string $root): array
{
    $controllerDir = $root.'/src/Controller';
    $paths = glob($controllerDir.'/*Controller.php') ?: [];
    sort($paths);

    $relativePaths = [];
    $contents = [];
    foreach ($paths as $path) {
        $relativePaths[] = str_replace('\\', '/', substr($path, strlen($root) + 1));
        $source = file_get_contents($path);
        if (false !== $source) {
            $contents[] = $source;
        }
    }

    return [
        'paths' => $relativePaths,
        'content' => implode("\n", $contents),
    ];
}

$root = dirname(__DIR__, 2);
$routeSources = addressControllerRouteSources($root);

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    fwrite(STDOUT, json_encode([
        'component' => 'Addressing',
        'sources' => $routeSources['paths'],
        'routes' => addressRouteInventory($routeSources['content']),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
}
