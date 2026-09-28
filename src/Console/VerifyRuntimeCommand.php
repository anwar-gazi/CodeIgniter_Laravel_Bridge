<?php

namespace AnwarGazi\CiLaravelSupport\Console;

use AnwarGazi\CiLaravelSupport\Security\SecurityBaseline;
use Illuminate\Console\Command;

class VerifyRuntimeCommand extends Command
{
    protected $signature = 'ci-bridge:verify-runtime';

    protected $description = 'Verify the pinned PHP, Laravel, CommonMark, and security patch baseline';

    public function handle(SecurityBaseline $baseline): int
    {
        try {
            $baseline->assertSatisfied();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        $this->info('CI Laravel bridge runtime and security baseline verified.');

        return 0;
    }
}
