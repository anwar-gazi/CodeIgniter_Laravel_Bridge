<?php

namespace AnwarGazi\CiLaravelSupport\Tests\Unit;

use AnwarGazi\CiLaravelSupport\Bridge\BridgePathResolver;
use PHPUnit\Framework\TestCase;

class BridgePathResolverTest extends TestCase
{
    public function testItResolvesRelativePathsFromTheLaravelBasePath(): void
    {
        $resolver = new BridgePathResolver('/srv/application');

        self::assertSame(
            '/srv/application' . DIRECTORY_SEPARATOR . 'bootstrap/cache/routes.php',
            $resolver->resolve('bootstrap/cache/routes.php')
        );
    }

    /**
     * @dataProvider absolutePathProvider
     */
    public function testItPreservesAbsolutePaths(string $path): void
    {
        self::assertSame($path, (new BridgePathResolver('/srv/application'))->resolve($path));
    }

    public function absolutePathProvider(): array
    {
        return [
            'Unix' => ['/var/cache/routes.php'],
            'Windows drive' => ['C:\\application\\bootstrap\\cache\\routes.php'],
            'Windows UNC' => ['\\\\server\\share\\routes.php'],
        ];
    }

    public function testDefaultConfigCanBeLoadedWithoutLaravelPathHelpers(): void
    {
        $config = require __DIR__ . '/../../config/ci_laravel_bridge.php';

        self::assertSame('bootstrap/cache/ci-laravel-owned-routes.php', $config['manifest_path']);
        self::assertSame('routes/routes_laravel.php', $config['route_files'][0]['path']);
    }
}
