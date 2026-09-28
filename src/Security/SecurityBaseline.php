<?php

namespace AnwarGazi\CiLaravelSupport\Security;

use Illuminate\Foundation\Application;
use Illuminate\Mail\Message;
use Illuminate\Validation\Concerns\ValidatesAttributes;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\DocParser;

class SecurityBaseline
{
    /** @var bool */
    private static $verified = false;

    public function assertSatisfied(): void
    {
        if (self::$verified) {
            return;
        }

        if (PHP_VERSION_ID < 70300 || PHP_VERSION_ID >= 70400) {
            throw new \RuntimeException('The CI Laravel bridge requires PHP 7.3.x.');
        }

        if (Application::VERSION !== '8.83.29') {
            throw new \RuntimeException('The CI Laravel bridge requires Laravel Framework 8.83.29 exactly.');
        }

        if (CommonMarkConverter::VERSION !== '1.4.3') {
            throw new \RuntimeException('The CI Laravel bridge requires league/commonmark 1.4.3 exactly.');
        }

        $this->assertSourceContains(Message::class, [
            'ensureAddressIsSafe',
            'Email addresses may not contain line break characters.',
        ]);
        $this->assertSourceContains(ValidatesAttributes::class, [
            "preg_match('/[\\r\\n]/', (string) \$value)",
        ]);
        $this->assertSourceContains(DocParser::class, [
            'assertInputWithinResourceLimits',
            '262144 byte safety limit',
            '2048 byte per-line safety limit',
        ]);

        if (class_exists('Illuminate\\Validation\\Rules\\File')) {
            throw new \RuntimeException('The unsupported later-version Laravel file-rule implementation is present.');
        }

        if (class_exists('Illuminate\\Filesystem\\LocalFilesystemAdapter')) {
            throw new \RuntimeException('The unsupported later-version Laravel local filesystem adapter is present.');
        }

        self::$verified = true;
    }

    private function assertSourceContains(string $class, array $needles): void
    {
        $reflection = new \ReflectionClass($class);
        $path = $reflection->getFileName();

        if (!is_string($path) || !is_readable($path)) {
            throw new \RuntimeException("Unable to verify the security baseline for [{$class}].");
        }

        $source = file_get_contents($path);
        if (!is_string($source)) {
            throw new \RuntimeException("Unable to read the security baseline source for [{$class}].");
        }

        foreach ($needles as $needle) {
            if (strpos($source, $needle) === false) {
                throw new \RuntimeException(
                    "Required security patch marker [{$needle}] is missing from [{$class}]."
                );
            }
        }
    }
}
