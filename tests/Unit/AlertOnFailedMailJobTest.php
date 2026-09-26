<?php

namespace Tests\Unit;

use App\Listeners\AlertOnFailedMailJob;
use App\Mail\OperationalAlertMail;
use App\Services\MailFailureAlert;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class AlertOnFailedMailJobTest extends TestCase
{
    public function test_it_sends_a_sanitized_alert_for_permanently_failed_mail(): void
    {
        config(['services.alerts.webhook_url' => 'https://alerts.example.test/hook']);
        Http::fake(['alerts.example.test/*' => Http::response([], 204)]);

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn(['displayName' => 'App\\Mail\\OrderPlacedMail']);
        $job->shouldReceive('getQueue')->andReturn('default');

        (new AlertOnFailedMailJob)->handle(new JobFailed('database', $job, new \RuntimeException('secret detail')));

        Http::assertSent(fn ($request) => $request['event'] === 'mail_job_failed'
            && $request['job'] === 'App\\Mail\\OrderPlacedMail'
            && $request['exception'] === \RuntimeException::class
            && ! str_contains($request->body(), 'secret detail'));
    }

    public function test_it_ignores_non_mail_jobs(): void
    {
        config(['services.alerts.webhook_url' => 'https://alerts.example.test/hook']);
        Http::fake();

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn(['displayName' => 'App\\Jobs\\SyncInventory']);
        $job->shouldReceive('resolveName')->never();

        (new AlertOnFailedMailJob)->handle(new JobFailed('database', $job, new \RuntimeException));

        Http::assertNothingSent();
    }

    public function test_it_alerts_when_a_queued_password_reset_notification_fails(): void
    {
        config(['services.alerts.webhook_url' => 'https://alerts.example.test/hook']);
        Http::fake(['alerts.example.test/*' => Http::response([], 204)]);

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn(['displayName' => 'App\\Notifications\\ResetPasswordNotification']);
        $job->shouldReceive('getQueue')->andReturn('default');

        (new AlertOnFailedMailJob)->handle(new JobFailed('database', $job, new \RuntimeException('sensitive SMTP detail')));

        Http::assertSent(fn ($request) => $request['event'] === 'mail_job_failed'
            && $request['job'] === 'App\\Notifications\\ResetPasswordNotification'
            && ! str_contains($request->body(), 'sensitive SMTP detail'));
    }

    public function test_enqueue_failures_are_reported_without_exception_details(): void
    {
        config(['services.alerts.webhook_url' => 'https://alerts.example.test/hook']);
        Http::fake(['alerts.example.test/*' => Http::response([], 204)]);

        app(MailFailureAlert::class)->report(
            'mail_enqueue_failed',
            'App\\Mail\\OrderPlacedMail',
            new \RuntimeException('SMTP password must never leave the server'),
            ['order_id' => 42],
        );

        Http::assertSent(fn ($request) => $request['event'] === 'mail_enqueue_failed'
            && $request['job'] === 'App\\Mail\\OrderPlacedMail'
            && $request['order_id'] === 42
            && ! str_contains($request->body(), 'SMTP password must never leave the server'));
    }

    public function test_it_uses_a_synchronous_sanitized_email_fallback(): void
    {
        config([
            'services.alerts.webhook_url' => null,
            'services.alerts.mail_to' => 'operator@example.test',
        ]);
        Mail::fake();

        app(MailFailureAlert::class)->report(
            'mail_enqueue_failed',
            'App\\Mail\\OrderPlacedMail',
            new \RuntimeException('password=must-not-leave-server'),
            ['order_id' => 42, 'token' => 'must-not-be-included'],
        );

        Mail::assertSent(OperationalAlertMail::class, fn (OperationalAlertMail $mail) => $mail->hasTo('operator@example.test')
            && $mail->exceptionClass === \RuntimeException::class
            && $mail->details === ['order_id' => 42]
        );
        Mail::assertNothingQueued();
    }

    public function test_it_never_recursively_alerts_for_the_operational_alert_itself(): void
    {
        config(['services.alerts.mail_to' => 'operator@example.test']);
        Mail::fake();

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn(['displayName' => OperationalAlertMail::class]);

        (new AlertOnFailedMailJob)->handle(new JobFailed('database', $job, new \RuntimeException));

        Mail::assertNothingSent();
    }
}
