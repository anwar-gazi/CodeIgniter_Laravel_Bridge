<?php
namespace AnwarGazi\CiLaravelSupport;

/**
 * Route Class
 *
 * Laravel-style routing engine for CodeIgniter 3.
 * Supports fluent registrations, route naming, and dynamic parameter parsing.
 */
class Route
{
    /** @var RouteInstance[] List of registered RouteInstance objects */
    private static $routes = [];

    /** @var array Named routes map: [name => RouteInstance] */
    private static $namedRoutes = [];

    /**
     * Register a GET route.
     */
    public static function get(string $uri, string $action): RouteInstance
    {
        return self::addRoute(['get'], $uri, $action);
    }

    /**
     * Register a POST route.
     */
    public static function post(string $uri, string $action): RouteInstance
    {
        return self::addRoute(['post'], $uri, $action);
    }

    public static function put(string $uri, string $action): RouteInstance
    {
        return self::addRoute(['put'], $uri, $action);
    }

    public static function patch(string $uri, string $action): RouteInstance
    {
        return self::addRoute(['patch'], $uri, $action);
    }

    public static function delete(string $uri, string $action): RouteInstance
    {
        return self::addRoute(['delete'], $uri, $action);
    }

    public static function options(string $uri, string $action): RouteInstance
    {
        return self::addRoute(['options'], $uri, $action);
    }

    /**
     * Register an ANY route (matches all methods).
     */
    public static function any(string $uri, string $action): RouteInstance
    {
        return self::addRoute(['get', 'head', 'post', 'put', 'patch', 'delete', 'options'], $uri, $action);
    }

    /**
     * Add route helper.
     */
    private static function addRoute(array $methods, string $uri, string $action): RouteInstance
    {
        $routeInstance = new RouteInstance($methods, $uri, $action);
        self::$routes[] = $routeInstance;
        return $routeInstance;
    }

    /**
     * Map a named route.
     */
    public static function registerNamedRoute(string $name, RouteInstance $routeInstance)
    {
        self::$namedRoutes[$name] = $routeInstance;
    }

    /**
     * Reset the route registry (mainly for unit tests).
     */
    public static function reset()
    {
        self::$routes = [];
        self::$namedRoutes = [];
    }

    /**
     * Compile registered routes into CodeIgniter 3 format.
     *
     * @return array
     */
    public static function compile(): array
    {
        $ciRoutes = [];
        foreach (self::$routes as $route) {
            // Expand optional parameters into multiple route entries
            $expandedUris = self::expandOptionalUri($route->uri);

            foreach ($expandedUris as $uri) {
                // Translate placeholder syntax to CI3 wildcards
                $ciUri = self::translateToCiWildcards($uri);

                $action = $route->action;
                // If action does not already contain backreferences ($1, $2, etc.)
                if (strpos($action, '$') === false) {
                    $wildcardCount = substr_count($ciUri, '(:num)') + substr_count($ciUri, '(:any)');
                    if ($wildcardCount > 0) {
                        $placeholders = [];
                        for ($i = 1; $i <= $wildcardCount; $i++) {
                            $placeholders[] = '$' . $i;
                        }
                        $action .= '/' . implode('/', $placeholders);
                    }
                }

                // CI3 supports verb-specific routes using a nested method map.
                // Keeping every verb prevents same-URI routes from overwriting
                // one another (for example GET /items and POST /items).
                foreach ($route->methods as $method) {
                    $ciRoutes[$ciUri][strtolower($method)] = $action;
                }
            }
        }
        return $ciRoutes;
    }

    /**
     * Expand optional URI parameters.
     * E.g. 'takeaways/{area}/{cuisine?}' -> ['takeaways/{area}', 'takeaways/{area}/{cuisine}']
     */
    public static function expandOptionalUri(string $uri): array
    {
        $uris = [];
        if (preg_match('/\{([a-zA-Z0-9_]+)(?::[a-zA-Z0-9_]+)?\?\}/', $uri, $matches)) {
            $placeholder = $matches[0]; // e.g. '{cuisine?}'
            $paramName = $matches[1];

            // 1. Without optional parameter
            $without = str_replace('/' . $placeholder, '', $uri);
            $without = str_replace($placeholder, '', $without);
            $without = rtrim($without, '/');

            // 2. With parameter (stripped of the question mark)
            $cleanPlaceholder = str_replace('?', '', $placeholder);
            $with = str_replace($placeholder, $cleanPlaceholder, $uri);

            $uris = array_merge(self::expandOptionalUri($without), self::expandOptionalUri($with));
        } else {
            $uris[] = $uri;
        }
        return $uris;
    }

    /**
     * Translate Laravel-style parameter braces to CI3 wildcards.
     * E.g. {id:num} -> (:num), {slug} -> (:any)
     */
    public static function translateToCiWildcards(string $uri): string
    {
        $uri = preg_replace('/\{[a-zA-Z0-9_]+:num\}/', '(:num)', $uri);
        $uri = preg_replace('/\{[a-zA-Z0-9_]+:any\}/', '(:any)', $uri);
        $uri = preg_replace('/\{[a-zA-Z0-9_]+\}/', '(:any)', $uri);
        return $uri;
    }

    /**
     * Resolve a named route to a URL path.
     *
     * @param  string $name
     * @param  array  $parameters
     * @return string
     * @throws \Exception
     */
    public static function resolve(string $name, array $parameters = []): string
    {
        if (!isset(self::$namedRoutes[$name])) {
            throw new \Exception("Route [{$name}] not defined.");
        }

        $route = self::$namedRoutes[$name];
        $uri = $route->uri;

        // Parse placeholders
        preg_match_all('/\{([a-zA-Z0-9_]+)(?::[a-zA-Z0-9_]+)?(\?)?\}/', $uri, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $fullPlaceholder = $match[0];
            $paramName = $match[1];
            $isOptional = isset($match[2]) && $match[2] === '?';

            if (array_key_exists($paramName, $parameters)) {
                $value = $parameters[$paramName];
                $uri = str_replace($fullPlaceholder, $value, $uri);
                unset($parameters[$paramName]);
            } elseif ($isOptional) {
                $uri = str_replace('/' . $fullPlaceholder, '', $uri);
                $uri = str_replace($fullPlaceholder, '', $uri);
            } else {
                throw new \InvalidArgumentException("Missing required route parameter [{$paramName}] for route [{$name}].");
            }
        }

        $uri = preg_replace('#/+#', '/', $uri);
        $uri = trim($uri, '/');

        if (!empty($parameters)) {
            $uri .= '?' . http_build_query($parameters);
        }

        return site_url($uri);
    }

    /**
     * Convert a Laravel-style route pattern to a regex pattern.
     */
    public static function patternToRegex(string $pattern): string
    {
        $pattern = trim($pattern, '/');

        // Escape regex special characters, but preserve placeholder braces {}, :, and ?
        $escaped = preg_replace('/([\\\\.+*^\$\[\]()|])/', '\\\\$1', $pattern);

        // Replace optional parameters with leading slash: "/{param?}" or "/{param:type?}"
        $escaped = preg_replace_callback('/\/\{([a-zA-Z0-9_]+)(?::([a-zA-Z0-9_]+))?\?\}/', function ($matches) {
            $name = $matches[1];
            $type = $matches[2] ?? 'any';
            $pattern = ($type === 'num') ? '[0-9]+' : '[^/]+';
            return "(?:/(?P<{$name}>{$pattern}))?";
        }, $escaped);

        // Replace optional parameters without leading slash: "{param?}" or "{param:type?}"
        $escaped = preg_replace_callback('/\{([a-zA-Z0-9_]+)(?::([a-zA-Z0-9_]+))?\?\}/', function ($matches) {
            $name = $matches[1];
            $type = $matches[2] ?? 'any';
            $pattern = ($type === 'num') ? '[0-9]+' : '[^/]+';
            return "(?P<{$name}>{$pattern})?";
        }, $escaped);

        // Replace required parameters: "{param}" or "{param:type}"
        $escaped = preg_replace_callback('/\{([a-zA-Z0-9_]+)(?::([a-zA-Z0-9_]+))?\}/', function ($matches) {
            $name = $matches[1];
            $type = $matches[2] ?? 'any';
            $pattern = ($type === 'num') ? '[0-9]+' : '[^/]+';
            return "(?P<{$name}>{$pattern})";
        }, $escaped);

        return '#^' . $escaped . '$#i';
    }

    /**
     * Find a registered route that matches the given URI and HTTP method.
     *
     * @param  string $uriString
     * @param  string $method
     * @return array|null  ['route' => RouteInstance, 'parameters' => array] or null
     */
    public static function findMatch(string $uriString, string $method): ?array
    {
        $uriString = trim($uriString, '/');
        $method = strtolower($method);

        foreach (self::$routes as $route) {
            if (!in_array($method, $route->methods, true)) {
                continue;
            }

            $regex = self::patternToRegex($route->uri);
            if (preg_match($regex, $uriString, $matches)) {
                // Filter out numeric keys from preg_match results to keep only named parameters
                $parameters = array_filter($matches, function ($key) {
                    return is_string($key);
                }, ARRAY_FILTER_USE_KEY);

                return [
                    'route'      => $route,
                    'parameters' => $parameters,
                ];
            }
        }

        return null;
    }
}

/**
 * RouteInstance Class
 *
 * Represents a registered route.
 */
class RouteInstance
{
    /** @var array */
    public $methods;

    /** @var string */
    public $uri;

    /** @var string */
    public $action;

    /** @var string|null */
    public $name;

    public function __construct(array $methods, string $uri, string $action)
    {
        $this->methods = $methods;
        $this->uri     = $uri;
        $this->action  = $action;
    }

    /**
     * Assign a name to the route.
     *
     * @param  string $name
     * @return $this
     */
    public function name(string $name): self
    {
        $this->name = $name;
        Route::registerNamedRoute($name, $this);
        return $this;
    }
}
