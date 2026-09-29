<?php
namespace AnwarGazi\CiLaravelSupport;

use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\UrlGenerator as UrlGeneratorContract;

/** Installs services required by Laravel's global helpers inside CI3. */
final class CodeIgniterCompatibility
{
    public static function boot(): Container
    {
        $container = Container::getInstance();
        if ($container === null) {
            $container = new Container();
            Container::setInstance($container);
        }

        if (!$container->bound('url')) {
            $container->instance('url', new CiUrlGenerator());
        }

        if (!$container->bound(UrlGeneratorContract::class)) {
            $container->alias('url', UrlGeneratorContract::class);
        }

        if (!$container->bound('redirect')) {
            $container->instance('redirect', new CiRedirector($container->make('url')));
        }

        return $container;
    }
}
