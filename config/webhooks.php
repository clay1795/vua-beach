<?php

return [
    /*
    | Webhooks are public endpoints. Keep their request bodies/query strings
    | small enough to reject memory-amplification attempts before validation.
    */
    'max_payload_bytes' => (int) env('WEBHOOK_MAX_PAYLOAD_BYTES', 65536),

    /*
    | A worker may die after claiming a webhook and before recording its result.
    | Only receipts older than this threshold may be reclaimed by a retry.
    */
    'processing_timeout_seconds' => (int) env('WEBHOOK_PROCESSING_TIMEOUT_SECONDS', 300),
];
