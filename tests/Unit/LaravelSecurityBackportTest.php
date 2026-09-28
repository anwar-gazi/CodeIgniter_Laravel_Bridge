<?php

namespace AnwarGazi\CiLaravelSupport\Tests\Unit;

use Illuminate\Mail\Message;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Swift_Message;

class LaravelSecurityBackportTest extends TestCase
{
    public function testDefaultEmailRuleRejectsLineBreakInjection(): void
    {
        $translator = new Translator(new ArrayLoader(), 'en');
        $validator = new Validator(
            $translator,
            ['email' => "\"sender\r\nBcc: victim@example.com\"@example.com"],
            ['email' => ['email']]
        );

        self::assertFalse($validator->passes());
    }

    /**
     * @dataProvider unsafeMailAddressCalls
     */
    public function testMailAddressEntryPointsRejectLineBreakInjection(string $method, array $arguments): void
    {
        $message = new Message(new Swift_Message());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('line break');

        call_user_func_array([$message, $method], $arguments);
    }

    public function unsafeMailAddressCalls(): array
    {
        $unsafe = "sender@example.com\r\nBcc: victim@example.com";

        return [
            'from' => ['from', [$unsafe]],
            'sender' => ['sender', [$unsafe]],
            'return path' => ['returnPath', [$unsafe]],
            'to' => ['to', [$unsafe]],
            'to override' => ['to', [$unsafe, null, true]],
            'cc' => ['cc', [$unsafe]],
            'cc override' => ['cc', [$unsafe, null, true]],
            'bcc' => ['bcc', [$unsafe]],
            'bcc override' => ['bcc', [$unsafe, null, true]],
            'reply to' => ['replyTo', [$unsafe]],
            'associative recipient array' => ['to', [[$unsafe => 'Sender']]],
            'numeric recipient array' => ['to', [[$unsafe]]],
        ];
    }

    public function testAffectedLaterLaravelFeaturesAreAbsent(): void
    {
        self::assertFalse(class_exists('Illuminate\\Validation\\Rules\\File'));
        self::assertFalse(class_exists('Illuminate\\Filesystem\\LocalFilesystemAdapter'));
    }
}
