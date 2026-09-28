<?php

namespace AnwarGazi\CiLaravelSupport\Bridge;

use Illuminate\Routing\RouteCollectionInterface;
use Symfony\Component\Routing\RouteCompiler;

class RouteOwnershipGenerator
{
    /** @var RouteCollectionInterface */
    private $routes;

    public function __construct(RouteCollectionInterface $routes)
    {
        $this->routes = $routes;
    }

    public function generate(
        array $sourceFiles = [],
        bool $claimMethodMismatches = true,
        bool $allowFallbackRoutes = false
    ): array {
        $entries = [];

        foreach ($this->routes->getRoutes() as $route) {
            if (!empty($route->isFallback) && !$allowFallbackRoutes) {
                throw new \LogicException(
                    'Laravel fallback routes cannot be cached while CodeIgniter routes still share the front controller.'
                );
            }

            $symfonyRoute = $route->toSymfonyRoute();
            if ($symfonyRoute->getCondition() !== '') {
                throw new \LogicException(
                    'Conditional Symfony routes are not supported by the pre-framework ownership matcher.'
                );
            }

            $compiled = RouteCompiler::compile($symfonyRoute);
            $methods = array_values(array_unique(array_map('strtoupper', $route->methods())));
            $schemes = array_values(array_unique(array_map('strtolower', $symfonyRoute->getSchemes())));

            $entries[] = [
                'name' => $route->getName(),
                'uri' => $route->uri(),
                'methods' => $methods,
                'schemes' => $schemes,
                'path_regex' => $compiled->getRegex(),
                'host_regex' => $compiled->getHostRegex() ?: '',
            ];
        }

        $sourceHash = $this->hashSources($sourceFiles);

        return [
            'format' => RouteOwnershipMatcher::FORMAT_VERSION,
            'generated_at' => gmdate('c'),
            'route_count' => count($entries),
            'claim_method_mismatches' => $claimMethodMismatches,
            'source_hash' => $sourceHash,
            'routes' => $entries,
            'checksum' => RouteOwnershipMatcher::checksum($entries, $claimMethodMismatches, $sourceHash),
        ];
    }

    private function hashSources(array $sourceFiles): string
    {
        $files = array_values(array_unique($sourceFiles));
        sort($files);

        $context = hash_init('sha256');

        foreach ($files as $file) {
            if (!is_file($file) || !is_readable($file)) {
                throw new \RuntimeException("Unable to hash Laravel route source [{$file}].");
            }

            hash_update($context, $file . "\0");
            hash_update_file($context, $file);
        }

        return hash_final($context);
    }
}
