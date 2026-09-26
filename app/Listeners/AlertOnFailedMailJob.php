<?php

namespace App\Listeners;

use App\Services\MailFailureAlert;
use Illuminate\Queue\Events\JobFailed;

class AlertOnFailedMailJob
{
    public function handle(JobFailed $event): void
    {
        $payload = $event->job->payload();
        $jobName = (string) ($payload['displayName'] ?? $event->job->resolveName());
        if ($jobName === 'App\\Mail\\OperationalAlertMail') {
            return;
        }
        if (! str_contains($jobName, 'Mail') && ! str_starts_with($jobName, 'App\\Notifications\\')) {
            return;
        }

        app(MailFailureAlert::class)->report('mail_job_failed', $jobName, $event->exception, [
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
        ]);
    }
}
