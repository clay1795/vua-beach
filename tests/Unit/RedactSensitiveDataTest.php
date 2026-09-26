<?php

namespace Tests\Unit;

use App\Logging\RedactSensitiveData;
use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use RuntimeException;
use Tests\TestCase;

class RedactSensitiveDataTest extends TestCase
{
    public function test_it_redacts_sensitive_context_message_urls_and_card_numbers(): void
    {
        $handler = new TestHandler;
        $logger = new IlluminateLogger(new Logger('security-test', [$handler]));
        (new RedactSensitiveData)($logger);

        $logger->info(
            'Authorization: Bearer live-token-123 https://shop.test/callback?secret=top-secret card 4111 1111 1111 1111',
            [
                'password' => 'StrongButPrivate!123',
                'nested' => ['access_token' => 'access-value', 'safe_order_id' => 42],
                'transaction_no' => 'PAYMENT-123',
                'safe' => 'visible',
                'exception' => new RuntimeException('SMTP failed: password=mail-secret token=top-secret'),
                'object' => new class
                {
                    public string $accessToken = 'object-secret';
                },
            ],
        );

        $record = $handler->getRecords()[0];
        $this->assertStringNotContainsString('live-token-123', $record->message);
        $this->assertStringNotContainsString('top-secret', $record->message);
        $this->assertStringNotContainsString('4111 1111 1111 1111', $record->message);
        $this->assertSame('[REDACTED]', $record->context['password']);
        $this->assertSame('[REDACTED]', $record->context['nested']['access_token']);
        $this->assertSame('[REDACTED]', $record->context['transaction_no']);
        $this->assertSame(42, $record->context['nested']['safe_order_id']);
        $this->assertSame('visible', $record->context['safe']);
        $this->assertSame(RuntimeException::class, $record->context['exception']['class']);
        $this->assertStringNotContainsString('mail-secret', $record->context['exception']['message']);
        $this->assertStringNotContainsString('top-secret', $record->context['exception']['message']);
        $this->assertStringStartsWith('[OBJECT ', $record->context['object']);
        $this->assertStringNotContainsString('object-secret', $record->context['object']);
    }

    public function test_every_persistent_and_remote_log_channel_uses_the_redactor(): void
    {
        $logging = require base_path('config/logging.php');

        foreach (['single', 'daily', 'payment', 'ghn', 'mail', 'inventory', 'monthly', 'slack', 'papertrail', 'stderr', 'syslog', 'errorlog'] as $channel) {
            $this->assertContains(RedactSensitiveData::class, $logging['channels'][$channel]['tap'] ?? [], "Channel {$channel} must redact sensitive data.");
        }
    }
}
