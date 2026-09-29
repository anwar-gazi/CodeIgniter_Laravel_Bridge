<?php

namespace AnwarGazi\CiLaravelSupport {
    function base_url($path = '')
    {
        return 'http://ci.test/' . ltrim((string) $path, '/');
    }

    function site_url($path = '')
    {
        return 'http://ci.test/index.php/' . ltrim((string) $path, '/');
    }
}

namespace AnwarGazi\CiLaravelSupport\Tests\Unit {
    use AnwarGazi\CiLaravelSupport\CiUrlGenerator;
    use PHPUnit\Framework\TestCase;

    class CiUrlGeneratorTest extends TestCase
    {
        public function testBuildsAssetAndSiteUrlsThroughCodeIgniter(): void
        {
            $generator = new CiUrlGenerator();

            $this->assertSame(
                'http://ci.test/assets/app.css',
                $generator->asset('assets/app.css')
            );
            $this->assertSame(
                'https://ci.test/index.php/orders/42',
                $generator->to('orders', [42], true)
            );
        }
    }
}
