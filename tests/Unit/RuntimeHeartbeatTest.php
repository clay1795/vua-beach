<?php

namespace Tests\Unit;

use App\Services\RuntimeHeartbeat;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RuntimeHeartbeatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'health.runtime_heartbeat_max_age_seconds' => 120,
        ]);
        Cache::clear();
    }

    public function test_it_reports_missing_fresh_and_stale_runtime_heartbeats(): void
    {
        $heartbeat = app(RuntimeHeartbeat::class);

        $this->assertFalse($heartbeat->isFresh(RuntimeHeartbeat::QUEUE));
        $this->assertFalse($heartbeat->isFresh(RuntimeHeartbeat::SCHEDULER));

        $heartbeat->beat(RuntimeHeartbeat::QUEUE);
        $heartbeat->beat(RuntimeHeartbeat::SCHEDULER);

        $this->assertTrue($heartbeat->isFresh(RuntimeHeartbeat::QUEUE));
        $this->assertTrue($heartbeat->isFresh(RuntimeHeartbeat::SCHEDULER));

        $this->travel(121)->seconds();

        $this->assertFalse($heartbeat->isFresh(RuntimeHeartbeat::QUEUE));
        $this->assertFalse($heartbeat->isFresh(RuntimeHeartbeat::SCHEDULER));
    }

    public function test_it_rejects_unknown_runtime_services(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(RuntimeHeartbeat::class)->beat('unknown');
    }
}
