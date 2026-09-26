<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'self'; object-src 'none'");

        // isSecure() honours forwarded headers only when the calling address is
        // explicitly configured as a trusted proxy in bootstrap/app.php.
        if (app()->environment(['production', 'staging']) && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($response->isServerError()) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
