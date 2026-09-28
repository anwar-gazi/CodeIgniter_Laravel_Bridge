<?php

namespace AnwarGazi\CiLaravelSupport\Tests\Integration;

use PHPUnit\Framework\TestCase;

class DispatcherTest extends TestCase
{
    public function testOwnedRouteRunsThroughLaravelKernel(): void
    {
        self::assertSame('laravel-owned', $this->runProbe('/owned'));
    }

    public function testUnownedRouteContinuesIntoCodeIgniterBootstrap(): void
    {
        self::assertSame('ci-owned', $this->runProbe('/legacy'));
    }

    private function runProbe(string $path): string
    {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__) . '/Fixtures/dispatcher_probe.php')
            . ' '
            . escapeshellarg($path);

        $pipes = [];
        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            self::fail('Unable to start dispatcher integration probe.');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, (string) $stderr);

        return (string) $stdout;
    }
}
