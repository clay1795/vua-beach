<?php

return [
    // The queue worker loops every few seconds and the scheduler runs every
    // minute. Two minutes permits short deploy/restart windows while still
    // detecting a dead daemon promptly.
    'runtime_heartbeat_max_age_seconds' => (int) env('HEALTH_RUNTIME_HEARTBEAT_MAX_AGE_SECONDS', 120),
    'external_probe_cache_seconds' => (int) env('HEALTH_EXTERNAL_PROBE_CACHE_SECONDS', 60),
    'external_probe_connect_timeout_seconds' => (int) env('HEALTH_EXTERNAL_PROBE_CONNECT_TIMEOUT_SECONDS', 3),
    'external_probe_timeout_seconds' => (int) env('HEALTH_EXTERNAL_PROBE_TIMEOUT_SECONDS', 5),
];
