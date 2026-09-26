<?php

$trustedProxies = array_values(array_filter(array_map(
    trim(...),
    explode(',', (string) env('TRUSTED_PROXIES', '')),
)));

return [
    /*
     * Only addresses of reverse proxies controlled by the operator belong here.
     * Never use a wildcard on a public deployment: forwarded headers influence
     * the detected client IP, host and HTTPS scheme.
     */
    'trusted_proxies' => $trustedProxies,
];
