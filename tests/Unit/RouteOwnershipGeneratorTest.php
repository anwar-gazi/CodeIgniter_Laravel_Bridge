<?php

namespace AnwarGazi\CiLaravelSupport\Tests\Unit;

use AnwarGazi\CiLaravelSupport\Bridge\RouteOwnershipGenerator;
use AnwarGazi\CiLaravelSupport\Bridge\RouteOwnershipMatcher;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use PHPUnit\Framework\TestCase;

class RouteOwnershipGeneratorTest extends TestCase
{
    public function testItCompilesNativeLaravelRoutesIntoAStandaloneManifest(): void
    {
        $router = $this->router();
        $router->get('orders/{order}', function () {
            return 'ok';
        })->where('order', '[0-9]+')->name('orders.show');

        $manifest = (new RouteOwnershipGenerator($router->getRoutes()))->generate([], true, false);

        self::assertSame(1, $manifest['route_count']);
        self::assertSame('orders.show', $manifest['routes'][0]['name']);
        self::assertSame(['GET', 'HEAD'], $manifest['routes'][0]['methods']);
        self::assertSame('', $manifest['routes'][0]['host_regex']);
        self::assertTrue(
            (new RouteOwnershipMatcher())->matches($manifest, 'GET', 'example.com', '/orders/123')
        );
        self::assertFalse(
            (new RouteOwnershipMatcher())->matches($manifest, 'GET', 'example.com', '/orders/not-a-number')
        );
    }

    public function testItRejectsFallbackRoutesDuringCoexistence(): void
    {
        $router = $this->router();
        $router->fallback(function () {
            return 'fallback';
        });

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('fallback');

        (new RouteOwnershipGenerator($router->getRoutes()))->generate([], true, false);
    }

    public function testItGeneratesAManifestFromLaravelRouteCache(): void
    {
        $router = $this->router();
        $router->get('cached-route/{id}', 'CachedController@show')
            ->where('id', '[0-9]+')
            ->name('cached.show');

        $compiled = $router->getRoutes()->compile();
        $router->setCompiledRoutes($compiled);

        $manifest = (new RouteOwnershipGenerator($router->getRoutes()))->generate([], true, false);

        self::assertSame(1, $manifest['route_count']);
        self::assertSame('cached.show', $manifest['routes'][0]['name']);
        self::assertTrue(
            (new RouteOwnershipMatcher())->matches($manifest, 'GET', 'example.com', '/cached-route/42')
        );
    }

    private function router(): Router
    {
        $container = new Container();

        return new Router(new Dispatcher($container), $container);
    }
}
