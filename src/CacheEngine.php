<?php

namespace AnwarGazi\CiLaravelSupport;

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Cache\CacheManager;

/**
 * A singleton wrapper around illuminate/cache to provide
 * Laravel-style caching in CodeIgniter.
 */
class CacheEngine
{
    /**
     * @var CacheManager
     */
    protected static $manager;

    /**
     * Boot the caching engine.
     *
     * @param string $cachePath The absolute path to the cache directory.
     */
    public static function boot(string $cachePath)
    {
        if (static::$manager) {
            return;
        }

        $app = Container::getInstance();
        if (!$app) {
            $app = new Container();
            Container::setInstance($app);
        }

        // The CacheManager requires 'files' and 'config' bindings
        if (!$app->bound('files')) {
            $app->singleton('files', function () {
                return new Filesystem();
            });
        }

        // Bind basic configuration for the cache manager
        if (!$app->bound('config')) {
            $app->singleton('config', function () use ($cachePath) {
                return [
                    'cache.default' => 'file',
                    'cache.stores.file' => [
                        'driver' => 'file',
                        'path' => rtrim($cachePath, '/'),
                    ]
                ];
            });
        } else {
            // Extend existing config if it exists
            $config = $app->make('config');
            if (is_array($config)) {
                $config['cache.default'] = 'file';
                $config['cache.stores.file'] = [
                    'driver' => 'file',
                    'path' => rtrim($cachePath, '/'),
                ];
                $app->instance('config', $config);
            } elseif (is_object($config) && method_exists($config, 'set')) {
                $config->set('cache.default', 'file');
                $config->set('cache.stores.file', [
                    'driver' => 'file',
                    'path' => rtrim($cachePath, '/'),
                ]);
            } else {
                throw new \RuntimeException('The container config binding must be an array or expose a set() method.');
            }
        }

        static::$manager = new CacheManager($app);

        // Bind 'cache' so the global cache() helper works seamlessly
        $app->instance('cache', static::$manager);
    }

    /**
     * Get the underlying CacheManager instance.
     *
     * @return CacheManager
     */
    public static function getManager()
    {
        return static::$manager;
    }

    /**
     * Forward static method calls directly to the default cache store.
     * This acts exactly like Laravel's Cache facade.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        if (!static::$manager) {
            // Default CI cache path if boot hasn't been explicitly called
            self::boot(FCPATH . 'application/cache');
        }

        return static::$manager->store()->$method(...$parameters);
    }
}
