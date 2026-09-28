<?php

namespace AnwarGazi\CiLaravelSupport\Tests\Unit;

use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CommonMarkSecurityBackportTest extends TestCase
{
    public function testLaravelMarkdownSupportRemainsAvailable(): void
    {
        self::assertSame('1.4.3', CommonMarkConverter::VERSION);
        self::assertStringContainsString('<h1>Hello</h1>', Str::markdown('# Hello'));
    }

    public function testOverlongMarkdownLinesAreRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('per-line safety limit');

        Str::markdown(str_repeat('*', 2049));
    }

    public function testOversizedMarkdownDocumentsAreRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('262144 byte safety limit');

        Str::markdown(str_repeat("safe line\n", 30000));
    }
}
