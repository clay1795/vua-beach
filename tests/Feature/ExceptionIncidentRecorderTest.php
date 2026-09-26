<?php

namespace Tests\Feature;

use App\Mail\OperationalAlertMail;
use App\Models\ExceptionIncident;
use App\Services\ExceptionIncidentRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ExceptionIncidentRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deduplicates_exceptions_without_persisting_messages_or_traces(): void
    {
        config([
            'services.alerts.webhook_url' => null,
            'services.alerts.mail_to' => 'operator@example.test',
        ]);
        Mail::fake();
        $recorder = app(ExceptionIncidentRecorder::class);

        $first = $recorder->record(new RuntimeException('password=never-store-this'), null, true);
        $second = $recorder->record(new RuntimeException('a different secret message'), null, true);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame(2, $second?->occurrences);
        $this->assertDatabaseCount('exception_incidents', 1);
        $this->assertFalse(in_array('message', (new ExceptionIncident)->getFillable(), true));
        Mail::assertSentTimes(OperationalAlertMail::class, 1);
        Mail::assertSent(OperationalAlertMail::class, fn (OperationalAlertMail $mail) => ! str_contains($mail->render(), 'never-store-this')
            && ! str_contains($mail->render(), 'different secret')
        );
    }

    public function test_it_ignores_expected_http_errors(): void
    {
        Mail::fake();

        $incident = app(ExceptionIncidentRecorder::class)->record(new NotFoundHttpException, null, true);

        $this->assertNull($incident);
        $this->assertDatabaseCount('exception_incidents', 0);
        Mail::assertNothingSent();
    }

    public function test_staging_probe_records_alerts_and_closes_its_synthetic_incident(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');
        config([
            'services.alerts.webhook_url' => null,
            'services.alerts.mail_to' => 'operator@example.test',
        ]);
        Mail::fake();

        $this->artisan('vua-beach:incident-test')
            ->expectsOutput('Đã lưu, cảnh báo và đóng sự cố thử thành công.')
            ->assertSuccessful();

        $this->assertNotNull(ExceptionIncident::query()->first()?->resolved_at);
        Mail::assertSentTimes(OperationalAlertMail::class, 1);
    }

    public function test_production_release_can_probe_the_internal_tracker_when_sentry_is_absent(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'services.alerts.webhook_url' => null,
            'services.alerts.mail_to' => 'operator@example.test',
        ]);
        Mail::fake();

        $this->artisan('vua-beach:incident-test')->assertSuccessful();

        $this->assertNotNull(ExceptionIncident::query()->first()?->resolved_at);
        Mail::assertSentTimes(OperationalAlertMail::class, 1);
    }
}
