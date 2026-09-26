<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository;

class RuntimeHeartbeat
{
    public const QUEUE = 'queue';

    public const SCHEDULER = 'scheduler';

    public function __construct(private readonly Repository $cache) {}

    public function beat(string $service): void
    {
        $this->cache->put(
            $this->key($service),
            now()->timestamp,
            max(60, $this->maxAgeSeconds() * 3),
        );
    }

    public function isFresh(string $service): bool
    {
        $timestamp = $this->cache->get($this->key($service));
        if (! is_numeric($timestamp)) {
            return false;
        }

        $age = now()->timestamp - (int) $timestamp;

        return $age >= 0 && $age <= $this->maxAgeSeconds();
    }

    private function maxAgeSeconds(): int
    {
        return max(30, (int) config('health.runtime_heartbeat_max_age_seconds', 120));
    }

    private function key(string $service): string
    {
        if (! in_array($service, [self::QUEUE, self::SCHEDULER], true)) {
            throw new \InvalidArgumentException('Unknown runtime heartbeat service.');
        }

        return "health:runtime:{$service}:heartbeat";
    }
}
