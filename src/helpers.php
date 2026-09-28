<?php

/**
 * helpers.php
 *
 * Global helper functions for the anwargazi/ci-laravel-support package.
 * Auto-loaded by Composer via the "files" directive in composer.json.
 *
 * Notes:
 *  - redirect() is kept in the AnwarGazi\CiLaravelSupport\Helpers namespace to
 *    avoid conflict with CodeIgniter 3's native redirect() in url_helper.php.
 *  - Class aliases are registered for Route, RouteInstance, EloquentEngine,
 *    Cache, ComponentAttributeBag, and Laravel_Controller so existing CI files
 *    can use them without namespace imports.
 *
 * PHP requires all namespace statements to come before or after global code when
 * using bracketed namespace syntax. This file uses the bracketed form throughout.
 */

// ---------------------------------------------------------------------------
// Global namespace: helper functions
// ---------------------------------------------------------------------------

namespace {

    use AnwarGazi\CiLaravelSupport\BladeEngine;
    use AnwarGazi\CiLaravelSupport\EloquentEngine;
    use AnwarGazi\CiLaravelSupport\Route;
    use AnwarGazi\CiLaravelSupport\RouteInstance;
    use AnwarGazi\CiLaravelSupport\LaravelController;
    use AnwarGazi\CiLaravelSupport\LaravelResponseBuilder;
    use AnwarGazi\CiLaravelSupport\Request;

    if (!function_exists('laravel_view')) {
        /**
         * Render a Blade template and return the HTML string.
         */
        function laravel_view(string $view, array $data = []): string
        {
            return BladeEngine::render($view, $data);
        }
    }

    if (!function_exists('view')) {
        /**
         * Create a Blade view response.
         */
        function view(string $view, array $data = []): \AnwarGazi\CiLaravelSupport\View
        {
            return new \AnwarGazi\CiLaravelSupport\View($view, $data);
        }
    }

    if (!function_exists('laravel_response')) {
        /**
         * Create a fluent Laravel-style response builder.
         */
        function laravel_response(): LaravelResponseBuilder
        {
            return new LaravelResponseBuilder();
        }
    }

    if (!function_exists('response')) {
        /**
         * Create a fluent Laravel-style response builder.
         */
        function response($content = null, int $status = 200): LaravelResponseBuilder
        {
            $builder = new LaravelResponseBuilder();
            if ($content !== null) {
                return $builder->setContent($content, $status);
            }
            return $builder;
        }
    }

    if (!function_exists('laravel_abort')) {
        /**
         * Send an HTTP error response and halt execution.
         */
        function laravel_abort(int $code, string $message = ''): void
        {
            http_response_code($code);
            if ($message) {
                echo $message;
            }
            exit;
        }
    }

    if (!function_exists('laravel_url')) {
        /**
         * Build an absolute site URL. Mirrors Laravel's url() helper.
         */
        function laravel_url(string $path = ''): string
        {
            return site_url($path);
        }
    }

    if (!function_exists('laravel_asset')) {
        /**
         * Build an asset URL. Mirrors Laravel's asset() helper.
         */
        function laravel_asset(string $path = ''): string
        {
            return base_url($path);
        }
    }

    if (!function_exists('laravel_config')) {
        /**
         * Read a CodeIgniter config item. Mirrors Laravel's config() helper.
         */
        function laravel_config(string $key, $default = null)
        {
            $CI =& get_instance();
            $value = $CI->config->item($key);
            return ($value !== null) ? $value : $default;
        }
    }

    if (!function_exists('route')) {
        /**
         * Generate the URL to a named route.
         */
        function route(string $name, array $parameters = []): string
        {
            return Route::resolve($name, $parameters);
        }
    }

    if (!function_exists('request')) {
        /**
         * Get the request instance or an input item.
         *
         * @return Request|mixed
         */
        function request(string $key = null, $default = null)
        {
            $req = new Request();
            if ($key === null) {
                return $req;
            }
            return $req->input($key, $default);
        }
    }

    if (!function_exists('app')) {
        /**
         * Get the available container instance or resolve a dependency.
         *
         * @param  string|null  $abstract
         * @param  array  $parameters
         * @return mixed|\Illuminate\Container\Container
         */
        function app($abstract = null, array $parameters = [])
        {
            $container = \Illuminate\Container\Container::getInstance();

            // Lazy-load the global instance if it hasn't been set up yet
            if (is_null($container)) {
                $container = new \Illuminate\Container\Container();
                \Illuminate\Container\Container::setInstance($container);
            }

            if (is_null($abstract)) {
                return $container;
            }

            return $container->make($abstract, $parameters);
        }
    }

    if (!function_exists('cache')) {
        /**
         * Get the cache instance or a value from the cache.
         *
         * @param  string|null  $key
         * @param  mixed  $default
         * @return mixed|\Illuminate\Cache\CacheManager
         */
        function cache($key = null, $default = null)
        {
            try {
                $cache = \AnwarGazi\CiLaravelSupport\CacheEngine::getManager();
            } catch (\Exception $e) {
                $cache = null;
            }

            if (!$cache) {
                $basePath = defined('FCPATH') ? FCPATH : rtrim(realpath(__DIR__ . '/../../'), '/') . '/';
                \AnwarGazi\CiLaravelSupport\CacheEngine::boot($basePath . 'application/cache');
                $cache = \AnwarGazi\CiLaravelSupport\CacheEngine::getManager();
            }

            if (is_null($key)) {
                return $cache;
            }

            return $cache->get($key, $default);
        }
    }

    if (!function_exists('restaurant_image_url')) {
        /**
         * Get the URL for a restaurant logo/featured image dynamically by restaurant ID.
         *
         * @param  int|string|null $restaurantId
         * @return string
         */
        function restaurant_image_url($restaurantId, $logo): string
        {
            $CI =& get_instance();
            if (method_exists($CI->config, 'load')) {
                $CI->config->load('assets_config', FALSE, TRUE);
            }

            if (empty($restaurantId)) {
                return '';
            }

            $logoDir = laravel_config('restaurant_logo_dir');
            $logoCustomDir = laravel_config('restaurant_logo_custom_dir');

            // Ensure trailing slash
            $logoDir = rtrim($logoDir, '/') . '/';
            $logoCustomDir = rtrim($logoCustomDir, '/') . '/';

            // 1. Check ID-based path directly: images/logos/{id}.jpg
            $pathId = $logoDir . $restaurantId . '.jpg';
            if (file_exists(FCPATH . $pathId)) {
                return base_url($pathId);
            }

            // 2. Query database for custom logo filename if not found directly
            // $logo = \Illuminate\Database\Capsule\Manager::table('restaurant')
            //     ->where('id', $restaurantId)
            //     ->value('logo');
            if (!empty($logo)) {
                if (filter_var($logo, FILTER_VALIDATE_URL)) {
                    return $logo;
                }

                $path1 = $logoCustomDir . $logo;
                if (file_exists(FCPATH . $path1)) {
                    return base_url($path1);
                }

                $path2 = $logoDir . $logo;
                if (file_exists(FCPATH . $path2)) {
                    return base_url($path2);
                }
            }

            return '';
        }
    }

    if (!function_exists('restaurant_url')) {
        /**
         * Get the menu URL for a restaurant by its ID or slug.
         *
         * @param  string $slug
         * @return string
         */
        function restaurant_url(string $slug): string
        {
            return base_url($slug . '/menu');
        }
    }

    if (!function_exists('cuisine_image_url')) {
        /**
         * Get the URL for a cuisine featured image dynamically from filesystem.
         *
         * @param  string $cuisineName
         * @return string
         */
        function cuisine_image_url(string $cuisineName): string
        {
            $CI =& get_instance();
            if (method_exists($CI->config, 'load')) {
                $CI->config->load('assets_config', FALSE, TRUE);
            }

            $cuisineDir = laravel_config('cuisine_image_dir', 'images/cuisine_image/');
            $cuisineDir = rtrim($cuisineDir, '/') . '/';

            // Normalize name: e.g. "Fish & Chips" -> "fish-and-chips", "Fast Food" -> "fast-food"
            $normalizedName = strtolower(trim($cuisineName));
            $normalizedName = str_replace([' & ', ' and ', '_', ' '], ['-and-', '-and-', '-', '-'], $normalizedName);
            $normalizedName = preg_replace('/[^a-z0-9\-]/', '', $normalizedName);
            $normalizedName = str_replace('--', '-', $normalizedName);

            $dir = FCPATH . $cuisineDir;

            if (is_dir($dir)) {
                static $files = null;
                if ($files === null) {
                    $files = array_filter(scandir($dir), function ($file) use ($dir) {
                        return is_file($dir . $file) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file);
                    });
                }

                // 1. Direct match (e.g. "indian" === "indian")
                foreach ($files as $file) {
                    $nameWithoutExt = pathinfo($file, PATHINFO_FILENAME);
                    $normalizedFile = strtolower($nameWithoutExt);
                    $normalizedFile = str_replace([' & ', ' and ', '_', ' '], ['-and-', '-and-', '-', '-'], $normalizedFile);
                    $normalizedFile = preg_replace('/[^a-z0-9\-]/', '', $normalizedFile);
                    $normalizedFile = str_replace('--', '-', $normalizedFile);

                    if ($normalizedFile === $normalizedName) {
                        return base_url($cuisineDir . $file);
                    }
                }

                // 2. Smart fallback partial match using Jaccard index and word overlap scoring
                $bestMatchFile = null;
                $bestScore = -1.0;

                $wordsQuery = array_filter(explode('-', $normalizedName));

                foreach ($files as $file) {
                    $nameWithoutExt = pathinfo($file, PATHINFO_FILENAME);
                    $normalizedFile = strtolower($nameWithoutExt);
                    $normalizedFile = str_replace([' & ', ' and ', '_', ' '], ['-and-', '-and-', '-', '-'], $normalizedFile);
                    $normalizedFile = preg_replace('/[^a-z0-9\-]/', '', $normalizedFile);
                    $normalizedFile = str_replace('--', '-', $normalizedFile);

                    if ($normalizedFile === '') {
                        continue;
                    }

                    $wordsFile = array_filter(explode('-', $normalizedFile));
                    $intersection = array_intersect($wordsQuery, $wordsFile);
                    $intersectSize = count($intersection);

                    if ($intersectSize > 0) {
                        $unionSize = count(array_unique(array_merge($wordsQuery, $wordsFile)));
                        $jaccard = $intersectSize / $unionSize;

                        // Overlap coefficient: measures how much of the shorter word set is matched
                        $overlapCoeff = $intersectSize / min(count($wordsQuery), count($wordsFile));

                        // Base score is Jaccard similarity.
                        $score = $jaccard;

                        // If there is a complete subset overlap (e.g. all words of "sri-lankan" in "sri-lankan-cuisine"), give a heavy bonus
                        if ($overlapCoeff === 1.0) {
                            $score += 2.0;
                        }

                        // Give extra preference if the file starts with the query
                        if (strpos($normalizedFile, $normalizedName) === 0) {
                            $score += 0.5;
                        }

                        if ($score > $bestScore) {
                            $bestScore = $score;
                            $bestMatchFile = $file;
                        }
                    }
                }

                if ($bestMatchFile !== null && $bestScore >= 0.3) {
                    return base_url($cuisineDir . $bestMatchFile);
                }
            }

            return base_url($cuisineDir . "fallback.png");
        }
    }

    // ---------------------------------------------------------------------------
    // Class aliases — provide backward-compatible unnamespaced access for
    // config files (hooks.php, routes.php) that use bare class names.
    // BladeEngine and ComponentAttributeBag are NOT aliased — callers use FQN.
    // ---------------------------------------------------------------------------

    if (!class_exists('ComponentAttributeBag', false)) {
        class_alias(\AnwarGazi\CiLaravelSupport\ComponentAttributeBag::class, 'ComponentAttributeBag');
    }
    if (!class_exists('Route', false)) {
        class_alias(Route::class, 'Route');
    }
    if (!class_exists('RouteInstance', false)) {
        class_alias(RouteInstance::class, 'RouteInstance');
    }
    if (!class_exists('EloquentEngine', false)) {
        class_alias(EloquentEngine::class, 'EloquentEngine');
    }
    if (!class_exists('Cache', false)) {
        class_alias(\AnwarGazi\CiLaravelSupport\CacheEngine::class, 'Cache');
    }
    if (!class_exists('Laravel_Controller', false)) {
        // CI_Controller may not exist when Composer's files autoloader runs, so
        // register this alias lazily when legacy code first requests it.
        spl_autoload_register(function ($class) {
            if (strcasecmp($class, 'Laravel_Controller') !== 0 || !class_exists('CI_Controller')) {
                return;
            }

            class_alias(LaravelController::class, 'Laravel_Controller');
        });
    }
}

// ---------------------------------------------------------------------------
// AnwarGazi\CiLaravelSupport\Helpers namespace:
// redirect() — kept here to avoid conflict with CI3's url_helper redirect()
// ---------------------------------------------------------------------------

namespace AnwarGazi\CiLaravelSupport\Helpers {

    use AnwarGazi\CiLaravelSupport\LaravelResponseBuilder;

    if (!function_exists('AnwarGazi\CiLaravelSupport\Helpers\redirect')) {
        /**
         * Laravel-style redirect function.
         *
         * Namespaced to avoid conflict with CI3's native redirect() in url_helper.php.
         *
         * Usage in controllers:
         *   use function AnwarGazi\CiLaravelSupport\Helpers\redirect;
         *
         * With a target URL:
         *   redirect('/home', 302);   // sends Location header and exits
         *
         * Without a URL (fluent):
         *   redirect()->route('area.takeaways', ['town' => 'aberdeen'], 301);
         *
         * @param  string|null $to      Destination URL or CI site path
         * @param  int         $status  HTTP redirect code (301 or 302)
         * @return LaravelResponseBuilder|void
         */
        function redirect(string $to = null, int $status = 302)
        {
            $builder = new LaravelResponseBuilder();
            if ($to === null) {
                return $builder;
            }
            $builder->redirect($to, $status);
        }
    }
}
