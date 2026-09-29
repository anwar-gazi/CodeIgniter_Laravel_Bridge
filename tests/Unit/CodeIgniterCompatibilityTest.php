<?php

namespace AnwarGazi\CiLaravelSupport\Tests\Unit;

use AnwarGazi\CiLaravelSupport\CiRedirector;
use AnwarGazi\CiLaravelSupport\CiUrlGenerator;
use AnwarGazi\CiLaravelSupport\CodeIgniterCompatibility;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\UrlGenerator as UrlGeneratorContract;
use PHPUnit\Framework\TestCase;

class CodeIgniterCompatibilityTest extends TestCase
{
    public function testComposerBootstrapBindsLaravelHelpersToCiAdapters(): void
    {
        $container = CodeIgniterCompatibility::boot();

        $this->assertInstanceOf(CiUrlGenerator::class, $container->make('url'));
        $this->assertSame($container->make('url'), $container->make(UrlGeneratorContract::class));
        $this->assertInstanceOf(CiRedirector::class, $container->make('redirect'));
        $this->assertSame($container, Container::getInstance());
    }

    public function testDoesNotReplaceExistingApplicationServices(): void
    {
        $container = new Container();
        $existingUrl = new \stdClass();
        $existingRedirect = new \stdClass();
        $container->instance('url', $existingUrl);
        $container->instance('redirect', $existingRedirect);
        Container::setInstance($container);

        CodeIgniterCompatibility::boot();

        $this->assertSame($existingUrl, $container->make('url'));
        $this->assertSame($existingRedirect, $container->make('redirect'));

        Container::setInstance(null);
        CodeIgniterCompatibility::boot();
    }

    public function testRedirectorSupportsLaravelAndLegacyCiArguments(): void
    {
        $redirector = new CiRedirector(new CiUrlGenerator());

        $this->assertSame(
            ['method' => 'location', 'code' => 307, 'headers' => ['X-Test' => 'yes']],
            $redirector->parameters(307, ['X-Test' => 'yes'])
        );
        $this->assertSame(
            ['method' => 'location', 'code' => 301, 'headers' => []],
            $redirector->parameters('location', 301)
        );
    }
}
