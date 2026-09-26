<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class HealthCheckService
{
    public function __construct(
        private readonly GHNService $ghn,
        private readonly MomoService $momo,
        private readonly VnpayService $vnpay,
        private readonly RuntimeHeartbeat $heartbeat,
    ) {}

    /** @return array{healthy:bool,checks:array<string,array{ok:bool,latency_ms:int,skipped?:bool}>} */
    public function run(): array
    {
        $checks = [
            'database' => $this->measure(fn () => DB::select('select 1')),
            'cache' => $this->measure(function (): void {
                $key = 'health:'.Str::uuid();
                Cache::put($key, 'ok', 10);
                if (Cache::get($key) !== 'ok') {
                    throw new \RuntimeException('Cache read-after-write failed.');
                }
                Cache::forget($key);
            }),
            'queue' => $this->measure(function (): void {
                $connection = (string) config('queue.default');
                $driver = (string) config("queue.connections.{$connection}.driver");
                if (in_array($driver, ['', 'sync', 'null', 'deferred', 'background'], true)) {
                    throw new \RuntimeException('Asynchronous queue is not active.');
                }
                if ($driver === 'database' && ! Schema::hasTable((string) config("queue.connections.{$connection}.table", 'jobs'))) {
                    throw new \RuntimeException('Queue table is missing.');
                }
                if (config('queue.failed.driver') === 'database-uuids' && ! Schema::hasTable((string) config('queue.failed.table', 'failed_jobs'))) {
                    throw new \RuntimeException('Failed queue table is missing.');
                }
                if (! $this->heartbeat->isFresh(RuntimeHeartbeat::QUEUE)) {
                    throw new \RuntimeException('Queue worker heartbeat is stale.');
                }
            }),
            'scheduler' => $this->measure(function (): void {
                if (! $this->heartbeat->isFresh(RuntimeHeartbeat::SCHEDULER)) {
                    throw new \RuntimeException('Scheduler heartbeat is stale.');
                }
            }),
            'mail' => $this->measure(function (): void {
                if (config('mail.default') !== 'smtp') {
                    throw new \RuntimeException('SMTP is not active.');
                }
                Cache::remember('health:external:mail', 60, function (): bool {
                    $transport = Mail::mailer()->getSymfonyTransport();
                    if (method_exists($transport, 'start')) {
                        $transport->start();
                    }
                    if (method_exists($transport, 'stop')) {
                        $transport->stop();
                    }

                    return true;
                });
            }),
            'ghn' => $this->measureOptional(
                filter_var(config('services.ghn.enabled'), FILTER_VALIDATE_BOOLEAN),
                function (): void {
                    $available = Cache::remember('health:external:ghn', 60, fn () => $this->ghn->configured() && ($this->ghn->provinces()['code'] ?? 0) === 200);
                    if (! $available) {
                        throw new \RuntimeException('GHN is unavailable.');
                    }
                },
            ),
            'momo' => $this->measureOptional(
                filter_var(config('services.momo.enabled'), FILTER_VALIDATE_BOOLEAN),
                function (): void {
                    if (! $this->momo->configured()) {
                        throw new \RuntimeException('MoMo is not configured.');
                    }
                    if (! $this->externalEndpointIsAvailable('momo', (string) config('services.momo.endpoint'))) {
                        throw new \RuntimeException('MoMo is unavailable.');
                    }
                },
            ),
            'vnpay' => $this->measureOptional(
                filter_var(config('services.vnpay.enabled'), FILTER_VALIDATE_BOOLEAN),
                function (): void {
                    if (! $this->vnpay->configured()) {
                        throw new \RuntimeException('VNPAY is not configured.');
                    }
                    if (! $this->externalEndpointIsAvailable('vnpay', (string) config('services.vnpay.url'))) {
                        throw new \RuntimeException('VNPAY is unavailable.');
                    }
                },
            ),
        ];

        return [
            'healthy' => collect($checks)->every(fn (array $check) => $check['ok']),
            'checks' => $checks,
        ];
    }

    /** @return array{ok:bool,latency_ms:int} */
    private function measure(callable $callback): array
    {
        $startedAt = hrtime(true);
        try {
            $callback();
            $ok = true;
        } catch (Throwable) {
            $ok = false;
        }

        return ['ok' => $ok, 'latency_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000)];
    }

    /** @return array{ok:bool,latency_ms:int,skipped?:bool} */
    private function measureOptional(bool $enabled, callable $callback): array
    {
        if (! $enabled) {
            return ['ok' => true, 'latency_ms' => 0, 'skipped' => true];
        }

        return $this->measure($callback);
    }

    private function externalEndpointIsAvailable(string $provider, string $endpoint): bool
    {
        $cacheSeconds = max(10, (int) config('health.external_probe_cache_seconds', 60));
        $cacheKey = 'health:external:'.$provider.':'.hash('sha256', $endpoint);

        return Cache::remember($cacheKey, $cacheSeconds, function () use ($endpoint): bool {
            $response = Http::connectTimeout(max(1, (int) config('health.external_probe_connect_timeout_seconds', 3)))
                ->timeout(max(2, (int) config('health.external_probe_timeout_seconds', 5)))
                ->withOptions(['allow_redirects' => false])
                ->head($endpoint);

            return $response->status() >= 100 && $response->status() < 500;
        });
    }
}
