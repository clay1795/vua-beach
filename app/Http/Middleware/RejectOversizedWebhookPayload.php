<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectOversizedWebhookPayload
{
    public function handle(Request $request, Closure $next): Response
    {
        $maximum = max(1024, (int) config('webhooks.max_payload_bytes', 65536));
        $contentLength = $request->headers->get('Content-Length');
        $bodyLength = strlen($request->getContent());
        $queryLength = strlen((string) $request->server('QUERY_STRING', ''));

        if (($contentLength !== null && (! ctype_digit($contentLength) || (int) $contentLength > $maximum))
            || $bodyLength > $maximum
            || $queryLength > $maximum) {
            return new JsonResponse(['message' => 'Payload too large'], 413);
        }

        return $next($request);
    }
}
