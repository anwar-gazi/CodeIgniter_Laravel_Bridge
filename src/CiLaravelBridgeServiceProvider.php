<?php

namespace AnwarGazi\CiLaravelSupport;

use AnwarGazi\CiLaravelSupport\Bridge\RouteOwnershipGenerator;
use AnwarGazi\CiLaravelSupport\Bridge\RouteOwnershipManifestWriter;
use AnwarGazi\CiLaravelSupport\Console\CacheOwnedRoutesCommand;
use AnwarGazi\CiLaravelSupport\Console\VerifyRuntimeCommand;
use AnwarGazi\CiLaravelSupport\Security\SecurityBaseline;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class CiLaravelBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ci_laravel_bridge.php', 'ci_laravel_bridge');

        $this->app->singleton(SecurityBaseline::class);
        $this->app->make(SecurityBaseline::class)->assertSatisfied();

        $this->app->singleton(RouteOwnershipGenerator::class, function ($app) {
            return new RouteOwnershipGenerator($app['router']->getRoutes());
        });

        $this->app->singleton(RouteOwnershipManifestWriter::class);
    }

    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__ . '/../config/ci_laravel_bridge.php' => config_path('ci_laravel_bridge.php'),
        ], 'ci-laravel-bridge-config');

        $this->publishes([
            __DIR__ . '/../resources/bridge/index.php.stub' => base_path('bridge/index.php.stub'),
        ], 'ci-laravel-bridge-dispatcher');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheOwnedRoutesCommand::class,
                VerifyRuntimeCommand::class,
            ]);
        }

        if (!$this->app->routesAreCached()) {
            foreach ((array) config('ci_laravel_bridge.route_files', []) as $definition) {
                $this->loadRouteDefinition($router, $definition);
            }
        }
    }

    private function loadRouteDefinition(Router $router, $definition): void
    {
        if (!is_array($definition) || empty($definition['path'])) {
            throw new \InvalidArgumentException('Every Laravel bridge route definition requires a path.');
        }

        $path = $definition['path'];
        $required = !empty($definition['required']);

        if (!is_file($path)) {
            if ($required) {
                throw new \RuntimeException("Required Laravel bridge route file not found [{$path}].");
            }

            return;
        }

        $attributes = [];
        foreach (['middleware', 'prefix', 'domain', 'as'] as $attribute) {
            if (array_key_exists($attribute, $definition) && $definition[$attribute] !== null) {
                $attributes[$attribute] = $definition[$attribute];
            }
        }

        $router->group($attributes, $path);
    }
}
