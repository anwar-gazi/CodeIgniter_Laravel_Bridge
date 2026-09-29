<?php
namespace AnwarGazi\CiLaravelSupport;

/**
 * BladeEngine
 *
 * A singleton wrapper around illuminate/view that integrates Laravel's Blade
 * templating engine into a CodeIgniter 3 application.
 *
 * View resolution (dot-notation):
 *   'home.index'          →  application/views/home/index.blade.php
 *   'layouts.main'        →  application/views/layouts/main.blade.php
 *   'components.divider'  →  application/views/components/divider.blade.php
 *
 * Usage:
 *   \AnwarGazi\CiLaravelSupport\BladeEngine::render('home.index', ['title' => 'ChefOnline']);
 */
class BladeEngine
{
    /** @var \Illuminate\View\Factory|null */
    private static $factory = null;

    /** @var array  Map of moduleName => absolute views/ path */
    private static $moduleMap = [];

    /**
     * Bootstrap the Blade factory (runs once per request).
     */
    public static function boot(): void
    {
        if (static::$factory !== null) {
            return;
        }

        static::$moduleMap = static::discoverModuleViewPaths();
        $cachePath = APPPATH . 'cache/blade';

        // Ensure cache directory exists and is writable
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        // Wire up illuminate/view dependencies manually (no full Laravel container needed)
        $filesystem = new \Illuminate\Filesystem\Filesystem();
        $resolver   = new \Illuminate\View\Engines\EngineResolver();

        // Register PHP engine
        $resolver->register('php', function () {
            return new \Illuminate\View\Engines\PhpEngine();
        });

        // Register Blade engine with custom precompiler for <x-component> tags and @once directive
        $resolver->register('blade', function () use ($filesystem, $cachePath) {
            $compiler = new \Illuminate\View\Compilers\BladeCompiler($filesystem, $cachePath);

            // This compatibility layer compiles anonymous tags itself and does
            // not have a native Laravel Application container at compile time.
            $compiler->withoutComponentTags();

            // Register @once / @endonce directives for illuminate/view ^6.0 compatibility
            $compiler->directive('once', function ($expression) {
                $id = 'once_' . md5($expression ?: uniqid('', true));
                return "<?php if (! isset(\$__once_tokens)) { \$__once_tokens = []; } if (! isset(\$__once_tokens['{$id}'])) { \$__once_tokens['{$id}'] = true; ?>";
            });

            $compiler->directive('endonce', function () {
                return '<?php } ?>';
            });

            // Add custom precompiler to support <x-...> tag components
            $compiler->extend(function ($value) {
                return BladeEngine::compileTags($value);
            });

            return new \Illuminate\View\Engines\CompilerEngine($compiler);
        });

        // Register all module view paths AND application view paths as global paths.
        $allPaths = array_values(static::$moduleMap);
        if (is_dir(APPPATH . 'View/Components')) {
            array_unshift($allPaths, APPPATH . 'View/Components');
        }
        if (is_dir(APPPATH . 'View')) {
            array_unshift($allPaths, APPPATH . 'View');
        }
        array_unshift($allPaths, APPPATH . 'views'); // Ensure application/views is the primary search path

        $finder   = new \Illuminate\View\FileViewFinder($filesystem, $allPaths, ['blade.php', 'php']);

        foreach (static::$moduleMap as $moduleName => $viewsDir) {
            $finder->addNamespace($moduleName, $viewsDir);
        }

        $events = new \Illuminate\Events\Dispatcher();

        static::$factory = new \Illuminate\View\Factory($resolver, $finder, $events);

        // Laravel 8's native component compiler resolves the view factory from
        // the global container even when Blade is running inside CodeIgniter.
        $container = CodeIgniterCompatibility::boot();
        $container->instance(\Illuminate\Contracts\View\Factory::class, static::$factory);
        $container->instance('view', static::$factory);

        // Share a default empty ComponentAttributeBag globally to prevent undefined variable errors
        // when views/components are rendered directly without the compiler tag syntax
        static::$factory->share('attributes', new \AnwarGazi\CiLaravelSupport\ComponentAttributeBag());
    }

    /**
     * Precompile <x-...> custom tags into native Blade component calls.
     * Supports attributes, php attributes (prefixed with :), self-closing and nested tags.
     *
     * Example:
     *   <x-button url="http://chefonline.co.uk" :active="$isActive">Click Me</x-button>
     * Compiles to:
     *   <?php $__env->startComponent('components.button', ['url' => 'http://chefonline.co.uk', 'active' => $isActive]); ?>Click Me<?php echo $__env->renderComponent(); ?>
     *
     * @param  string $value  Raw template string
     * @return string         Compiled template string
     */
    public static function compileTags(string $value): string
    {
        // Matches self-closing <x-name attr="val" /> OR block <x-name attr="val">content</x-name>
        $pattern = '/<x-([a-zA-Z0-9_\-\.]+)(\s+[^>]*?)?\s*(?:\/>|>(.*?)<\/x-\1>)/s';

        $callback = function ($matches) {
            $component = $matches[1];
            $attributesStr = isset($matches[2]) ? trim($matches[2]) : '';
            $slot = isset($matches[3]) ? $matches[3] : '';

            $attributesArray = [];
            $bagArray = [];
            if ($attributesStr !== '') {
                // Match key="value", :key="expression", or valueless key (like `required`)
                preg_match_all('/(:?[a-zA-Z0-9_\-]+)(?:\s*=\s*"([^"]*)")?/', $attributesStr, $attrMatches, PREG_SET_ORDER);
                foreach ($attrMatches as $match) {
                    $name = $match[1];
                    $val = isset($match[2]) ? $match[2] : null;

                    if (strpos($name, ':') === 0) {
                        // PHP expression: :url="$var"
                        $cleanName = substr($name, 1);
                        $exportedName = var_export($cleanName, true);
                        $attributesArray[] = "{$exportedName} => {$val}";
                        $bagArray[] = "{$exportedName} => {$val}";
                    } else {
                        // String literal or valueless
                        $exportedName = var_export($name, true);
                        if ($val === null) {
                            $attributesArray[] = "{$exportedName} => true";
                            $bagArray[] = "{$exportedName} => true";
                        } else {
                            $exportedValue = var_export($val, true);
                            $attributesArray[] = "{$exportedName} => {$exportedValue}";
                            $bagArray[] = "{$exportedName} => {$exportedValue}";
                        }
                    }
                }
            }

            // Use FQN for ComponentAttributeBag so the compiled output works after namespacing
            $fqn = '\\AnwarGazi\\CiLaravelSupport\\ComponentAttributeBag';
            $bagPhp = "new {$fqn}([" . implode(', ', $bagArray) . '])';
            $attributesPhp = "['attributes' => {$bagPhp}" . (count($attributesArray) > 0 ? ', ' . implode(', ', $attributesArray) : '') . ']';

            // Check if it's a Class-Based Component
            $parts = array_map(function ($p) {
                return str_replace(' ', '', ucwords(str_replace('-', ' ', $p)));
            }, explode('.', $component));
            $componentClass = 'App\\View\\Components\\' . implode('\\', $parts);

            if (class_exists($componentClass)) {
                $camelAttributesArray = [];
                foreach ($attributesArray as $attrStr) {
                    if (preg_match('/^\'([^\']+)\'\s*=>\s*(.*)$/', $attrStr, $m)) {
                        $name = $m[1];
                        $val = $m[2];
                        $camelName = lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name))));
                        $camelAttributesArray[] = "'{$camelName}' => {$val}";
                    }
                }

                $camelAttributesPhp = "[" . implode(', ', $camelAttributesArray) . "]";

                return "<?php ob_start(); ?>{$slot}<?php
                    \$__slot = new \\Illuminate\\Support\\HtmlString(ob_get_clean());
                    \$__component = app('{$componentClass}', {$camelAttributesPhp});
                    \$__data = get_object_vars(\$__component);
                    \$__data['slot'] = \$__slot;
                    \$__data['attributes'] = {$bagPhp};
                    echo \$__env->make(\$__component->render(), \$__data)->render();
                ?>";
            }

            return "<?php \$__env->startComponent('components.{$component}', {$attributesPhp}); ?>{$slot}<?php echo \$__env->renderComponent(); ?>";
        };

        // Run iteratively to handle nested component tags (inside out), while
        // bounding malformed or adversarial templates.
        $previousValue = '';
        $iterations = 0;
        while ($value !== $previousValue && $iterations++ < 20) {
            $previousValue = $value;
            $value = preg_replace_callback($pattern, $callback, $value);
        }

        return $value;
    }

    /**
     * Render a Blade view and return the compiled HTML string.
     *
     * @param  string $view  Dot-notation view name
     * @param  array  $data  Variables to pass to the view
     * @return string
     */
    public static function render(string $view, array $data = []): string
    {
        static::boot();

        $resolved = static::resolveViewName($view);

        return static::$factory->make($resolved, $data)->render();
    }

    /**
     * Resolve a dot-notation view name to illuminate/view's internal format.
     *
     * 'website.home_view' → 'website::home_view'    (module namespace if module map exists)
     * 'layouts.main'      → 'layouts/main'           (subdirectory within views)
     *
     * @param  string $view
     * @return string
     */
    public static function resolveViewName(string $view): string
    {
        $dotPos = strpos($view, '.');
        if ($dotPos === false) {
            return $view;
        }

        $firstSegment = substr($view, 0, $dotPos);
        $remainder    = substr($view, $dotPos + 1);

        if (array_key_exists($firstSegment, static::$moduleMap)) {
            return $firstSegment . '::' . str_replace('.', '/', $remainder);
        }

        $path = str_replace('.', '/', $view);
        if (static::$factory !== null) {
            $finder = static::$factory->getFinder();
            try {
                $finder->find($path);
                return $path;
            } catch (\InvalidArgumentException $e) {
                // Try converting hyphenated or lowercase first segment to PascalCase (e.g. area-takeaways -> AreaTakeaways)
                $pascalSegment = str_replace(' ', '', ucwords(str_replace('-', ' ', $firstSegment)));
                $pascalPath = $pascalSegment . '/' . str_replace('.', '/', $remainder);
                try {
                    $finder->find($pascalPath);
                    return $pascalPath;
                } catch (\InvalidArgumentException $e2) {
                    return $path;
                }
            }
        }

        return $path;
    }

    /**
     * Discover all module view directories under application/modules/.
     * Returns a map of moduleName => absolute views/ path.
     *
     * @return array  ['website' => '/path/to/website/views', ...]
     */
    private static function discoverModuleViewPaths(): array
    {
        $map        = [];
        $modulesDir = APPPATH . 'modules';

        if (!is_dir($modulesDir)) {
            return $map;
        }

        foreach (new \DirectoryIterator($modulesDir) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }
            $viewsDir = $item->getPathname() . DIRECTORY_SEPARATOR . 'views';
            if (is_dir($viewsDir)) {
                $map[$item->getFilename()] = $viewsDir;
            }
        }

        return $map;
    }

    /**
     * Expose the underlying factory (for advanced use, e.g. registering directives).
     */
    public static function factory(): ?\Illuminate\View\Factory
    {
        static::boot();
        return static::$factory;
    }
}
