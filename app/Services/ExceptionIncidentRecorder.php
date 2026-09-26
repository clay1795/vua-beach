<?php

namespace App\Services;

use App\Models\ExceptionIncident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ExceptionIncidentRecorder
{
    public function __construct(private OperationalAlert $alerts) {}

    public function record(Throwable $exception, ?Request $request = null, bool $force = false): ?ExceptionIncident
    {
        if (! $force && ! app()->environment(['production', 'staging'])) {
            return null;
        }
        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
            return null;
        }

        $location = $this->applicationLocation($exception);
        $route = $this->routeIdentifier($request);
        $fingerprint = hash('sha256', implode('|', [$exception::class, $location, $route]));
        $now = now();

        try {
            [$incident, $shouldAlert] = DB::transaction(function () use ($exception, $location, $route, $fingerprint, $now): array {
                $inserted = DB::table('exception_incidents')->insertOrIgnore([
                    'fingerprint' => $fingerprint,
                    'exception_class' => $exception::class,
                    'location' => $location ?: null,
                    'route' => $route ?: null,
                    'environment' => mb_strimwidth(app()->environment(), 0, 32),
                    'occurrences' => 0,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $incident = ExceptionIncident::query()->where('fingerprint', $fingerprint)->lockForUpdate()->firstOrFail();
                $shouldAlert = $inserted === 1
                    || $incident->last_alerted_at === null
                    || $incident->last_alerted_at->lte($now->copy()->subMinutes(15));
                $incident->forceFill([
                    'occurrences' => $incident->occurrences + 1,
                    'last_seen_at' => $now,
                    'last_alerted_at' => $shouldAlert ? $now : $incident->last_alerted_at,
                    'resolved_at' => null,
                ])->save();

                return [$incident, $shouldAlert];
            }, 3);
        } catch (Throwable $recorderException) {
            Log::error('Internal exception tracker could not persist an incident.', [
                'exception' => $recorderException::class,
                'original_exception' => $exception::class,
            ]);

            return null;
        }

        if ($shouldAlert) {
            $this->alerts->report('exception_incident', $exception::class, $exception, [
                'fingerprint' => $fingerprint,
                'occurrences' => $incident->occurrences,
                'route' => $route ?: null,
                'location' => $location ?: null,
            ]);
        }

        return $incident;
    }

    private function applicationLocation(Throwable $exception): string
    {
        $candidates = [[
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ], ...$exception->getTrace()];

        foreach ($candidates as $frame) {
            $file = (string) ($frame['file'] ?? '');
            if ($file !== '' && str_starts_with($file, app_path().DIRECTORY_SEPARATOR)) {
                return str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).':'.(int) ($frame['line'] ?? 0);
            }
        }

        return 'framework-or-external';
    }

    private function routeIdentifier(?Request $request): string
    {
        if ($request === null) {
            return app()->runningInConsole() ? 'console' : 'unknown';
        }

        $route = $request->route();
        if (is_object($route)) {
            return (string) ($route->getName() ?: $route->uri());
        }

        return 'unmatched';
    }
}
