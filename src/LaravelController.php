<?php
namespace AnwarGazi\CiLaravelSupport;

/**
 * Laravel_Controller
 *
 * A Laravel-style base controller for CodeIgniter 3.
 * Extend this instead of CI_Controller when building new features
 * that use Blade templates or the laravel_response() fluent API.
 *
 * Usage:
 *   class MyController extends \AnwarGazi\CiLaravelSupport\LaravelController { ... }
 *
 * Available methods:
 *   $this->view(string $view, array $data)   — render a Blade template
 *   $this->laravel_response()                — fluent response builder
 */
class LaravelController extends \CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        // Ensure BladeEngine is booted (idempotent — safe to call multiple times)
        BladeEngine::boot();
    }

    /**
     * Render a Blade view and send it to the browser.
     *
     * View name uses dot-notation mirroring Laravel:
     *   'home.index'  →  application/views/home/index.blade.php
     *
     * @param  string $view
     * @param  array  $data
     * @return void
     */
    protected function view(string $view, array $data = []): void
    {
        echo BladeEngine::render($view, $data);
    }

    /**
     * Return a fluent Laravel-style response builder.
     *
     * Example:
     *   $this->laravel_response()->json(['status' => 'ok']);
     *   $this->laravel_response()->redirect('home');
     *
     * @return LaravelResponseBuilder
     */
    protected function laravel_response(): LaravelResponseBuilder
    {
        return new LaravelResponseBuilder();
    }

    /**
     * Intercept method execution to support route parameter binding.
     *
     * @param  string $method  Method name
     * @param  array  $params  Positional arguments from CodeIgniter's router
     * @return void
     */
    public function _remap($method, $params = array())
    {
        $CI =& get_instance();

        // 1. Get the current request URI and method
        $uriString = $CI->uri->uri_string();
        $httpMethod = $CI->input->method();

        // 2. Try to find a matching Laravel-style route
        $match = Route::findMatch($uriString, $httpMethod);

        if ($match) {
            $routeParams = $match['parameters'];
            $matchedMethod = $this->methodFromRouteAction($match['route']->action, $method);
            $this->invokeMethodWithBinding($matchedMethod, $routeParams);
        } else {
            // Fallback: Invoke the method with standard positional params.
            $this->invokeMethodWithBinding($method, $params, true);
        }
    }

    /**
     * Extract the controller method from a CI-style route action.
     *
     * Supports controller/method and module/controller/method actions. If an
     * action contains no method segment, CI's resolved method remains the
     * authoritative fallback.
     */
    protected function methodFromRouteAction(string $action, string $fallback): string
    {
        $segments = array_values(array_filter(explode('/', trim($action, '/')), 'strlen'));

        if (count($segments) < 2) {
            return $fallback;
        }

        return end($segments);
    }

    /**
     * Invoke the given method on the controller, resolving and binding parameters dynamically.
     *
     * @param  string $method
     * @param  array  $parameters  Route parameters (associative or positional)
     * @param  bool   $isPositional Whether parameters are positional (fallback mode)
     * @return void
     */
    protected function invokeMethodWithBinding(string $method, array $parameters, bool $isPositional = false): void
    {
        if (!method_exists($this, $method)) {
            show_404();
            return;
        }

        $refMethod = new \ReflectionMethod($this, $method);

        // Prevent accessing non-public methods or CI internal methods via routing
        if (!$refMethod->isPublic() || strpos($method, '_') === 0) {
            show_404();
            return;
        }
        $refParams = $refMethod->getParameters();
        $args = [];

        // For positional params, we keep a pointer
        $paramIndex = 0;

        foreach ($refParams as $refParam) {
            $paramName = $refParam->getName();
            $paramType = $refParam->getType();
            $paramClassName = ($paramType && !$paramType->isBuiltin()) ? $paramType->getName() : null;

            // 1. Check if we have a class name type-hint
            if ($paramClassName !== null && class_exists($paramClassName)) {
                if (is_a($paramClassName, Request::class, true) || $paramClassName === Request::class) {
                    $args[] = app(Request::class);
                } else {
                    $args[] = app($paramClassName);
                }
                continue;
            }

            $getValueOrDefault = function () use ($refParam) {
                if ($refParam->isDefaultValueAvailable()) {
                    return $refParam->getDefaultValue();
                }
                if ($refParam->allowsNull()) {
                    return null;
                }
                $type = $refParam->getType();
                if ($type && $type->isBuiltin()) {
                    switch ($type->getName()) {
                        case 'string': return '';
                        case 'int': return 0;
                        case 'float': return 0.0;
                        case 'bool': return false;
                        case 'array': return [];
                    }
                }
                return null;
            };

            // 2. Bind route parameters
            if ($isPositional) {
                if (array_key_exists($paramIndex, $parameters)) {
                    $args[] = $parameters[$paramIndex];
                    $paramIndex++;
                } else {
                    $args[] = $getValueOrDefault();
                }
            } else {
                if (array_key_exists($paramName, $parameters)) {
                    $args[] = $parameters[$paramName];
                } else {
                    $args[] = $getValueOrDefault();
                }
            }
        }
        $response = $refMethod->invokeArgs($this, $args);

        if ($response instanceof View) {
            echo $response->render();
        } elseif ($response instanceof LaravelResponseBuilder) {
            $response->send();
        } elseif (is_string($response)) {
            echo $response;
        } elseif (is_array($response)) {
            $this->laravel_response()->json($response);
        } elseif (is_object($response)) {
            if ($response instanceof \JsonSerializable) {
                $this->laravel_response()->json($response->jsonSerialize());
            } elseif (method_exists($response, 'resolve')) {
                $this->laravel_response()->json($response->resolve());
            } elseif (method_exists($response, 'toArray')) {
                $this->laravel_response()->json($response->toArray());
            } elseif (method_exists($response, '__toString')) {
                echo (string) $response;
            }
        }
    }
}
