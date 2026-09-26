<?php

use App\Services\RuntimeHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ghn:sync-orders --limit=50')
    ->everyFifteenMinutes()
    ->when(fn (): bool => filter_var(config('services.ghn.enabled'), FILTER_VALIDATE_BOOLEAN))
    ->withoutOverlapping();
Schedule::command('db:backup --keep=14')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->dailyAt('02:30')->withoutOverlapping();
Schedule::call(fn () => app(RuntimeHeartbeat::class)->beat(RuntimeHeartbeat::SCHEDULER))
    ->name('runtime-heartbeat')
    ->everyMinute()
    ->withoutOverlapping(2);
