<?php

namespace App\Services;

use Throwable;

class MailFailureAlert
{
    public function __construct(private OperationalAlert $alerts) {}

    /** @param array<string, int|string|null> $details */
    public function report(string $event, string $job, Throwable $exception, array $details = []): void
    {
        $this->alerts->report($event, $job, $exception, $details, 'mail');
    }
}
