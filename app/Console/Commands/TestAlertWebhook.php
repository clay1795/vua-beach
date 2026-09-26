<?php

namespace App\Console\Commands;

use App\Mail\OperationalAlertMail;
use App\Services\PublicEnvironmentReadiness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class TestAlertWebhook extends Command
{
    protected $signature = 'vua-beach:alert-test';

    protected $description = 'Gửi sự kiện thử đã làm sạch tới các kênh cảnh báo vận hành';

    public function handle(PublicEnvironmentReadiness $readiness): int
    {
        $webhook = config('services.alerts.webhook_url');
        $recipient = config('services.alerts.mail_to');
        $webhookReady = $readiness->publicHttpsUrl($webhook);
        $emailReady = is_string($recipient) && filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false;
        if (! $webhookReady && ! $emailReady) {
            $this->error('Cần ALERT_WEBHOOK_URL HTTPS công khai hoặc ALERT_MAIL_TO hợp lệ.');

            return self::FAILURE;
        }

        $failed = false;
        if ($webhookReady) {
            try {
                Http::asJson()
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->post((string) $webhook, [
                        'event' => 'monitoring_test',
                        'app' => config('app.name'),
                        'environment' => app()->environment(),
                        'sent_at' => now()->toIso8601String(),
                    ])
                    ->throw();
            } catch (\Throwable) {
                $failed = true;
            }
        }

        if ($emailReady) {
            try {
                Mail::to((string) $recipient)->send(new OperationalAlertMail(
                    'monitoring_test',
                    self::class,
                    'TestEvent',
                    ['connection' => 'console'],
                ));
            } catch (\Throwable) {
                $failed = true;
            }
        }

        if ($failed) {
            $this->error('Không gửi được cảnh báo thử qua tất cả kênh đã cấu hình.');

            return self::FAILURE;
        }

        $this->info('Đã gửi cảnh báo thử thành công.');

        return self::SUCCESS;
    }
}
