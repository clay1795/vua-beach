<?php

namespace Tests\Feature;

use App\Services\GHNService;
use App\Services\HealthCheckService;
use App\Services\MomoService;
use App\Services\RuntimeHeartbeat;
use App\Services\VnpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok_when_all_dependencies_are_healthy(): void
    {
        $this->mock(HealthCheckService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('run')->once()->andReturn([
                'healthy' => true,
                'checks' => ['database' => ['ok' => true, 'latency_ms' => 2]],
            ]);
        });

        $this->getJson(route('health'))
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.ok', true)
            ->assertJsonMissingPath('error');
    }

    public function test_health_endpoint_returns_service_unavailable_without_leaking_errors(): void
    {
        $this->mock(HealthCheckService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('run')->once()->andReturn([
                'healthy' => false,
                'checks' => ['mail' => ['ok' => false, 'latency_ms' => 10]],
            ]);
        });

        $this->getJson(route('health'))
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.mail.ok', false)
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('message');
    }

    public function test_runtime_health_requires_fresh_queue_and_scheduler_heartbeats(): void
    {
        config([
            'mail.default' => 'array',
            'queue.default' => 'database',
            'services.momo.enabled' => true,
            'services.momo.endpoint' => 'https://momo.example.test/create',
            'services.vnpay.enabled' => true,
            'services.vnpay.url' => 'https://vnpay.example.test/pay',
        ]);
        Http::fake(fn () => Http::response('', 405));
        $this->mock(GHNService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('configured')->once()->andReturnTrue();
            $mock->shouldReceive('provinces')->once()->andReturn(['code' => 200]);
        });
        $this->mock(MomoService::class, fn (MockInterface $mock) => $mock->shouldReceive('configured')->times(3)->andReturnTrue());
        $this->mock(VnpayService::class, fn (MockInterface $mock) => $mock->shouldReceive('configured')->times(3)->andReturnTrue());

        $health = app(HealthCheckService::class);
        $missing = $health->run();

        $this->assertFalse($missing['checks']['queue']['ok']);
        $this->assertFalse($missing['checks']['scheduler']['ok']);

        $heartbeat = app(RuntimeHeartbeat::class);
        $heartbeat->beat(RuntimeHeartbeat::QUEUE);
        $heartbeat->beat(RuntimeHeartbeat::SCHEDULER);
        $fresh = $health->run();

        $this->assertTrue($fresh['checks']['queue']['ok']);
        $this->assertTrue($fresh['checks']['scheduler']['ok']);

        config(['queue.default' => 'sync']);
        $synchronous = $health->run();
        $this->assertFalse($synchronous['checks']['queue']['ok']);
    }

    public function test_external_gateway_probe_distinguishes_reachable_and_unavailable_services(): void
    {
        config([
            'mail.default' => 'array',
            'services.momo.enabled' => true,
            'services.momo.endpoint' => 'https://momo.example.test/create',
            'services.vnpay.enabled' => true,
            'services.vnpay.url' => 'https://vnpay.example.test/pay',
        ]);
        $this->mock(GHNService::class, fn (MockInterface $mock) => $mock->shouldReceive('configured')->once()->andReturnFalse());
        $this->mock(MomoService::class, fn (MockInterface $mock) => $mock->shouldReceive('configured')->once()->andReturnTrue());
        $this->mock(VnpayService::class, fn (MockInterface $mock) => $mock->shouldReceive('configured')->once()->andReturnTrue());
        Http::fake([
            'https://momo.example.test/create' => Http::response('', 503),
            'https://vnpay.example.test/pay' => Http::response('', 405),
        ]);

        $result = app(HealthCheckService::class)->run();

        $this->assertFalse($result['checks']['momo']['ok']);
        $this->assertTrue($result['checks']['vnpay']['ok']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->method() === 'HEAD' && $request->url() === 'https://momo.example.test/create');
        Http::assertSent(fn ($request) => $request->method() === 'HEAD' && $request->url() === 'https://vnpay.example.test/pay');
    }

    public function test_disabled_optional_integrations_are_skipped_without_external_requests(): void
    {
        config([
            'mail.default' => 'array',
            'services.ghn.enabled' => false,
            'services.momo.enabled' => false,
            'services.vnpay.enabled' => false,
        ]);
        Http::fake();
        $this->mock(GHNService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('configured'));
        $this->mock(MomoService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('configured'));
        $this->mock(VnpayService::class, fn (MockInterface $mock) => $mock->shouldNotReceive('configured'));

        $result = app(HealthCheckService::class)->run();

        $this->assertTrue($result['checks']['ghn']['ok']);
        $this->assertTrue($result['checks']['ghn']['skipped']);
        $this->assertTrue($result['checks']['momo']['ok']);
        $this->assertTrue($result['checks']['momo']['skipped']);
        $this->assertTrue($result['checks']['vnpay']['ok']);
        $this->assertTrue($result['checks']['vnpay']['skipped']);
        Http::assertNothingSent();
    }
}
