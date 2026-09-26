<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_present_on_public_responses(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'self'; object-src 'none'")
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_production_https_response_enables_hsts(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->get('https://localhost/up')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_untrusted_client_cannot_spoof_https_with_forwarded_header(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.25',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('/up')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_configured_proxy_can_report_original_https_scheme(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['http_security.trusted_proxies' => ['10.0.0.8']]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.8',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('/up')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
