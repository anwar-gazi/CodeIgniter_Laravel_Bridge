<?php

namespace AnwarGazi\CiLaravelSupport\Tests\Unit;

use AnwarGazi\CiLaravelSupport\Security\SecurityBaseline;
use PHPUnit\Framework\TestCase;

class SecurityBaselineTest extends TestCase
{
    public function testInstalledRuntimeSatisfiesTheSecurityBaseline(): void
    {
        (new SecurityBaseline())->assertSatisfied();

        self::assertTrue(true);
    }
}
