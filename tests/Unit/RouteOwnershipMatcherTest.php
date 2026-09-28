<?php

namespace AnwarGazi\CiLaravelSupport\Tests\Unit;

use AnwarGazi\CiLaravelSupport\Bridge\RouteOwnershipMatcher;
use PHPUnit\Framework\TestCase;

class RouteOwnershipMatcherTest extends TestCase
{
    public function testItMatchesOwnedRoutesByPathHostMethodAndScheme(): void
    {
        $manifest = $this->manifest([
            [
                'name' => 'orders.show',
                'uri' => 'orders/{order}',
                'methods' => ['GET', 'HEAD'],
                'schemes' => ['https'],
                'path_regex' => '{^/orders/(?P<order>[0-9]+)$}sD',
                'host_regex' => '{^(?P<tenant>[^\\.]++)\\.example\\.com$}sD',
            ],
        ], false);

        $matcher = new RouteOwnershipMatcher();

        self::assertTrue($matcher->matches($manifest, 'GET', 'acme.example.com:443', '/orders/42?tab=one', 'https'));
        self::assertFalse($matcher->matches($manifest, 'POST', 'acme.example.com', '/orders/42', 'https'));
        self::assertFalse($matcher->matches($manifest, 'GET', 'other.test', '/orders/42', 'https'));
        self::assertFalse($matcher->matches($manifest, 'GET', 'acme.example.com', '/orders/42', 'http'));
    }

    public function testItCanClaimMethodMismatchesForLaravel405Handling(): void
    {
        $route = [
            'name' => 'orders.show',
            'uri' => 'orders/{order}',
            'methods' => ['GET', 'HEAD'],
            'schemes' => [],
            'path_regex' => '{^/orders/(?P<order>[0-9]+)$}sD',
            'host_regex' => '',
        ];

        $matcher = new RouteOwnershipMatcher();

        self::assertTrue($matcher->matches($this->manifest([$route], true), 'DELETE', '', '/orders/42'));
        self::assertFalse($matcher->matches($this->manifest([$route], false), 'DELETE', '', '/orders/42'));
    }

    public function testItRejectsTamperedManifests(): void
    {
        $manifest = $this->manifest([], true);
        $manifest['source_hash'] = 'tampered';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('checksum');

        (new RouteOwnershipMatcher())->validate($manifest);
    }

    public function testItMatchesRoutesBelowAConfiguredBaseUri(): void
    {
        $route = [
            'name' => 'orders.index',
            'uri' => 'orders',
            'methods' => ['GET', 'HEAD'],
            'schemes' => [],
            'path_regex' => '{^/orders$}sD',
            'host_regex' => '',
        ];

        $matcher = new RouteOwnershipMatcher();
        $manifest = $this->manifest([$route], true);

        self::assertTrue($matcher->matches($manifest, 'GET', 'example.com', '/legacy/orders', 'http', '/legacy'));
        self::assertFalse($matcher->matches($manifest, 'GET', 'example.com', '/orders', 'http', '/legacy'));
        self::assertFalse($matcher->matches($manifest, 'GET', 'example.com', '/legacy-other/orders', 'http', '/legacy'));
    }

    public function testItUsesLaravelUriDecodingAndTrailingSlashSemantics(): void
    {
        $route = [
            'name' => 'orders.show',
            'uri' => 'orders/{order}',
            'methods' => ['GET', 'HEAD'],
            'schemes' => [],
            'path_regex' => '{^/orders/(?P<order>[0-9]+)$}sD',
            'host_regex' => '',
        ];

        $matcher = new RouteOwnershipMatcher();
        $manifest = $this->manifest([$route], true);

        self::assertTrue($matcher->matches($manifest, 'GET', 'example.com', '/orders/42/'));
        self::assertTrue($matcher->matches($manifest, 'GET', 'example.com', '/orders/%34%32'));
        self::assertFalse($matcher->matches($manifest, 'GET', 'example.com', '/orders/4%2F2'));
        self::assertFalse($matcher->matches($manifest, 'GET', 'example.com', '/orders/42%2F'));
    }

    public function testItRejectsMalformedRouteEntries(): void
    {
        $manifest = $this->manifest([[
            'name' => null,
            'uri' => 'orders',
            'methods' => 'GET',
            'schemes' => [],
            'path_regex' => '{^/orders$}sD',
            'host_regex' => '',
        ]], true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid route entry');

        (new RouteOwnershipMatcher())->validate($manifest);
    }

    private function manifest(array $routes, bool $claimMethodMismatches): array
    {
        $sourceHash = hash('sha256', 'routes');

        return [
            'format' => RouteOwnershipMatcher::FORMAT_VERSION,
            'claim_method_mismatches' => $claimMethodMismatches,
            'source_hash' => $sourceHash,
            'routes' => $routes,
            'checksum' => RouteOwnershipMatcher::checksum($routes, $claimMethodMismatches, $sourceHash),
        ];
    }
}
