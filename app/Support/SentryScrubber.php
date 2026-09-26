<?php

namespace App\Support;

use Sentry\Event;

class SentryScrubber
{
    private const REDACTED = '[REDACTED]';

    public static function scrub(Event $event): Event
    {
        $request = $event->getRequest();
        unset($request['data'], $request['cookies']);

        foreach (['url', 'query_string'] as $field) {
            if (isset($request[$field]) && is_string($request[$field])) {
                $request[$field] = self::sanitizeLocation($request[$field]);
            }
        }

        if (is_array($request['headers'] ?? null)) {
            foreach ($request['headers'] as $name => $value) {
                if (is_string($name) && preg_match('/(?:authorization|cookie|token|secret|signature|secure.?hash|api.?key)/i', $name)) {
                    $request['headers'][$name] = self::REDACTED;
                }
            }
        }

        $event->setRequest($request);

        return $event;
    }

    private static function sanitizeLocation(string $value): string
    {
        $value = preg_replace('#(/dat-lai-mat-khau/)[^/?&\s]+#i', '$1'.self::REDACTED, $value) ?? $value;

        return preg_replace_callback(
            '/(^|[?&])([^=&]*?(?:token|secret|signature|secure_?hash|securehash|api_?key)[^=&]*=)[^&\s]*/i',
            fn (array $match): string => $match[1].$match[2].self::REDACTED,
            $value,
        ) ?? $value;
    }
}
