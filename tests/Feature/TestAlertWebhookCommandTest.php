<?php

namespace Tests\Feature;

use App\Mail\OperationalAlertMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TestAlertWebhookCommandTest extends TestCase
{
    public function test_it_refuses_an_invalid_or_local_webhook(): void
    {
        config([
            'services.alerts.webhook_url' => 'http://127.0.0.1/hook',
            'services.alerts.mail_to' => null,
        ]);
        Http::fake();

        $this->artisan('vua-beach:alert-test')
            ->expectsOutput('Cần ALERT_WEBHOOK_URL HTTPS công khai hoặc ALERT_MAIL_TO hợp lệ.')
            ->assertFailed();

        Http::assertNothingSent();
    }

    public function test_it_sends_a_sanitized_monitoring_event(): void
    {
        config([
            'services.alerts.webhook_url' => 'https://alerts.vuabeach.vn/hook',
            'services.alerts.mail_to' => null,
        ]);
        Http::fake(['alerts.vuabeach.vn/*' => Http::response([], 204)]);

        $this->artisan('vua-beach:alert-test')
            ->expectsOutput('Đã gửi cảnh báo thử thành công.')
            ->assertSuccessful();

        Http::assertSent(fn ($request) => $request['event'] === 'monitoring_test'
            && $request['app'] === config('app.name')
            && $request['environment'] === 'testing'
            && isset($request['sent_at'])
            && ! str_contains($request->body(), 'password')
            && ! str_contains($request->body(), 'token'));
    }

    public function test_it_can_verify_the_synchronous_email_alert_channel(): void
    {
        config([
            'services.alerts.webhook_url' => null,
            'services.alerts.mail_to' => 'operator@example.test',
        ]);
        Mail::fake();

        $this->artisan('vua-beach:alert-test')
            ->expectsOutput('Đã gửi cảnh báo thử thành công.')
            ->assertSuccessful();

        Mail::assertSent(OperationalAlertMail::class, fn (OperationalAlertMail $mail) => $mail->hasTo('operator@example.test')
            && $mail->event === 'monitoring_test'
            && $mail->exceptionClass === 'TestEvent'
        );
    }

    public function test_it_fails_when_the_alert_destination_rejects_the_event(): void
    {
        config([
            'services.alerts.webhook_url' => 'https://alerts.vuabeach.vn/hook',
            'services.alerts.mail_to' => null,
        ]);
        Http::fake(['alerts.vuabeach.vn/*' => Http::response([], 500)]);

        $this->artisan('vua-beach:alert-test')
            ->expectsOutput('Không gửi được cảnh báo thử qua tất cả kênh đã cấu hình.')
            ->assertFailed();
    }
}
