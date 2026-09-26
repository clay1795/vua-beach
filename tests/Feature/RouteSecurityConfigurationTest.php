<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class RouteSecurityConfigurationTest extends TestCase
{
    public function test_trusted_host_pattern_is_limited_to_the_configured_application_host(): void
    {
        config(['app.url' => 'https://shop.vuabeach.vn']);

        $this->assertSame(['^shop\\.vuabeach\\.vn$'], app(TrustHosts::class)->hosts());
    }

    public function test_every_admin_route_requires_auth_verified_admin_and_two_factor_middlewares(): void
    {
        $adminRoutes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with($route->uri(), 'admin'));

        $this->assertNotEmpty($adminRoutes);
        foreach ($adminRoutes as $route) {
            $middleware = $route->gatherMiddleware();
            foreach (['web', 'auth', 'verified', 'admin', 'admin.2fa'] as $required) {
                $this->assertContains($required, $middleware, "Route {$route->uri()} thiếu middleware {$required}.");
            }
        }
    }

    public function test_two_factor_management_routes_are_admin_only_and_mutations_are_rate_limited(): void
    {
        $routes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with((string) $route->getName(), 'two-factor.'));

        $this->assertCount(5, $routes);
        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            foreach (['web', 'auth', 'verified', 'admin'] as $required) {
                $this->assertContains($required, $middleware, "Route {$route->uri()} thiếu middleware {$required}.");
            }
            if (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                $this->assertContains('throttle:5,1', $middleware, "Route {$route->uri()} thiếu rate limit.");
            }
        }
    }

    public function test_auth_verification_and_webhook_entry_points_keep_required_rate_limits(): void
    {
        $expected = [
            'login.store' => 'throttle:login',
            'register.store' => 'throttle:registration',
            'verification.send' => 'throttle:verification-resend',
            'verification.verify' => 'throttle:6,1',
            'password.email' => 'throttle:password-reset-link',
            'password.update' => 'throttle:password-reset',
            'vnpay.ipn' => 'throttle:webhooks',
            'momo.ipn' => 'throttle:webhooks',
            'ghn.webhook' => 'throttle:webhooks',
        ];

        foreach ($expected as $name => $requiredMiddleware) {
            $route = RouteFacade::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Thiếu route {$name}.");
            $this->assertContains($requiredMiddleware, $route->gatherMiddleware(), "Route {$name} thiếu {$requiredMiddleware}.");
        }

        foreach (['vnpay.ipn', 'momo.ipn', 'ghn.webhook'] as $name) {
            $route = RouteFacade::getRoutes()->getByName($name);
            $this->assertContains('webhook.payload', $route->gatherMiddleware(), "Route {$name} thiếu giới hạn payload webhook.");
        }
    }

    public function test_sensitive_log_channels_use_restricted_file_permissions_and_sentry_disables_pii(): void
    {
        foreach (['single', 'daily', 'payment', 'ghn', 'mail', 'inventory'] as $channel) {
            $this->assertSame(0640, config("logging.channels.{$channel}.permission"), "Log channel {$channel} phải dùng quyền 0640.");
        }

        $this->assertFalse((bool) config('sentry.send_default_pii'));
        $this->assertSame('never', config('sentry.max_request_body_size'));
        $this->assertFalse((bool) config('sentry.breadcrumbs.sql_bindings'));
        $this->assertFalse((bool) config('sentry.tracing.sql_bindings'));
    }
}
