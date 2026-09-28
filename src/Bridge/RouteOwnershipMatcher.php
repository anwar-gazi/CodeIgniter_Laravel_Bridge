<?php

namespace AnwarGazi\CiLaravelSupport\Bridge;

class RouteOwnershipMatcher
{
    public const FORMAT_VERSION = 1;

    public function matches(
        array $manifest,
        string $method,
        string $host,
        string $path,
        string $scheme = 'http',
        string $baseUri = ''
    ): bool {
        $this->validate($manifest);

        $method = strtoupper($method);
        $host = $this->normalizeHost($host);
        $path = $this->normalizePath($path, $baseUri);
        if ($path === null) {
            return false;
        }
        $scheme = strtolower($scheme);
        $claimMethodMismatches = !empty($manifest['claim_method_mismatches']);

        foreach ($manifest['routes'] as $route) {
            if (!$this->matchesRegex($route['path_regex'], $path, 'path')) {
                continue;
            }

            if ($route['host_regex'] !== '' && !$this->matchesRegex($route['host_regex'], $host, 'host')) {
                continue;
            }

            if (!empty($route['schemes']) && !in_array($scheme, $route['schemes'], true)) {
                continue;
            }

            if ($claimMethodMismatches || empty($route['methods']) || in_array($method, $route['methods'], true)) {
                return true;
            }
        }

        return false;
    }

    public function validate(array $manifest): void
    {
        if (($manifest['format'] ?? null) !== self::FORMAT_VERSION) {
            throw new \RuntimeException('Unsupported Laravel route ownership manifest format.');
        }

        if (!isset($manifest['routes']) || !is_array($manifest['routes'])) {
            throw new \RuntimeException('Laravel route ownership manifest has no routes array.');
        }

        foreach ($manifest['routes'] as $route) {
            if (!is_array($route)
                || !array_key_exists('path_regex', $route)
                || !array_key_exists('host_regex', $route)
                || !array_key_exists('methods', $route)
                || !array_key_exists('schemes', $route)
                || !is_string($route['path_regex'])
                || !is_string($route['host_regex'])
                || !is_array($route['methods'])
                || !is_array($route['schemes'])
                || !$this->containsOnlyStrings($route['methods'])
                || !$this->containsOnlyStrings($route['schemes'])) {
                throw new \RuntimeException('Laravel route ownership manifest contains an invalid route entry.');
            }
        }

        if (!isset($manifest['checksum']) || !is_string($manifest['checksum'])) {
            throw new \RuntimeException('Laravel route ownership manifest has no checksum.');
        }

        $expected = self::checksum(
            $manifest['routes'],
            !empty($manifest['claim_method_mismatches']),
            (string) ($manifest['source_hash'] ?? '')
        );

        if (!hash_equals($expected, $manifest['checksum'])) {
            throw new \RuntimeException('Laravel route ownership manifest checksum is invalid.');
        }
    }

    public static function checksum(array $routes, bool $claimMethodMismatches, string $sourceHash): string
    {
        $payload = json_encode([
            'routes' => $routes,
            'claim_method_mismatches' => $claimMethodMismatches,
            'source_hash' => $sourceHash,
        ], JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            throw new \RuntimeException('Unable to encode Laravel route ownership manifest.');
        }

        return hash('sha256', $payload);
    }

    private function matchesRegex(string $regex, string $value, string $label): bool
    {
        $result = preg_match($regex, $value);

        if ($result === false) {
            throw new \RuntimeException("Laravel route ownership manifest contains an invalid {$label} regular expression.");
        }

        return $result === 1;
    }

    private function normalizePath(string $path, string $baseUri): ?string
    {
        $parsedPath = parse_url($path, PHP_URL_PATH);
        $path = is_string($parsedPath) ? $parsedPath : $path;

        if ($path === '') {
            $path = '/';
        }

        $path = '/' . ltrim($path, '/');
        $baseUri = '/' . trim($baseUri, '/');

        if ($baseUri !== '/') {
            if ($path === $baseUri) {
                $path = '/';
            } elseif (strpos($path, $baseUri . '/') === 0) {
                $path = substr($path, strlen($baseUri)) ?: '/';
            } else {
                return null;
            }
        }

        return rawurldecode(rtrim($path, '/') ?: '/');
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return '';
        }

        if ($host[0] === '[') {
            $closingBracket = strpos($host, ']');

            return $closingBracket === false ? $host : substr($host, 0, $closingBracket + 1);
        }

        return preg_replace('/:\d+$/', '', $host) ?: $host;
    }

    private function containsOnlyStrings(array $values): bool
    {
        foreach ($values as $value) {
            if (!is_string($value)) {
                return false;
            }
        }

        return true;
    }
}
