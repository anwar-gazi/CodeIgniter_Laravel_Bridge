<?php

namespace AnwarGazi\CiLaravelSupport\Console;

use AnwarGazi\CiLaravelSupport\Bridge\RouteOwnershipGenerator;
use AnwarGazi\CiLaravelSupport\Bridge\RouteOwnershipManifestWriter;
use Illuminate\Console\Command;

class CacheOwnedRoutesCommand extends Command
{
    protected $signature = 'ci-bridge:cache-routes {--path= : Override the generated manifest path}';

    protected $description = 'Generate the pre-framework Laravel route ownership manifest';

    public function handle(RouteOwnershipGenerator $generator, RouteOwnershipManifestWriter $writer): int
    {
        $path = $this->option('path') ?: config('ci_laravel_bridge.manifest_path');
        if (!is_string($path) || $path === '') {
            $this->error('The ci_laravel_bridge.manifest_path configuration value is required.');

            return 1;
        }

        $sourceFiles = [];
        foreach ((array) config('ci_laravel_bridge.route_files', []) as $definition) {
            if (!is_array($definition) || empty($definition['path'])) {
                $this->error('Every Laravel bridge route definition requires a path.');

                return 1;
            }

            if (!is_file($definition['path'])) {
                if (!empty($definition['required'])) {
                    $this->error("Required Laravel bridge route file not found [{$definition['path']}].");

                    return 1;
                }

                continue;
            }

            $sourceFiles[] = $definition['path'];
        }

        $manifest = $generator->generate(
            $sourceFiles,
            (bool) config('ci_laravel_bridge.claim_method_mismatches', true),
            (bool) config('ci_laravel_bridge.allow_fallback_routes', false)
        );

        $writer->write($path, $manifest);

        $this->info("Cached {$manifest['route_count']} Laravel-owned routes to {$path}.");

        return 0;
    }
}
