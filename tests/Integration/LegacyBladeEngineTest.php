<?php

namespace AnwarGazi\CiLaravelSupport\Tests\Integration;

use AnwarGazi\CiLaravelSupport\BladeEngine;
use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class LegacyBladeEngineTest extends TestCase
{
    /** @var string */
    private static $applicationPath;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$applicationPath = sys_get_temp_dir()
            . '/ci-laravel-blade-' . bin2hex(random_bytes(6)) . '/';

        mkdir(self::$applicationPath . 'views/components', 0777, true);
        mkdir(self::$applicationPath . 'cache/blade', 0777, true);

        file_put_contents(
            self::$applicationPath . 'views/components/notice.blade.php',
            '<aside {{ $attributes }}>{{ $slot }}</aside>'
        );
        file_put_contents(
            self::$applicationPath . 'views/page.blade.php',
            '<x-notice class="integration-check">Rendered through Laravel 8 Blade</x-notice>'
        );

        if (!defined('APPPATH')) {
            define('APPPATH', self::$applicationPath);
        }
    }

    public function testRendersAnonymousComponentInsideCodeIgniterRuntime(): void
    {
        $html = BladeEngine::render('page');

        $this->assertStringContainsString('integration-check', $html);
        $this->assertStringContainsString('Rendered through Laravel 8 Blade', $html);
        $this->assertSame(
            BladeEngine::factory(),
            Container::getInstance()->make(ViewFactoryContract::class)
        );
    }
}
