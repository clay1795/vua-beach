<?php

namespace Tests\Unit;

use App\Services\PaymentReturnUrlResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaymentReturnUrlResolverTest extends TestCase
{
    public function test_application_origin_follows_localhost_or_the_current_allowed_tunnel(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $resolver = app(PaymentReturnUrlResolver::class);

        $this->assertSame('http://localhost:8000', $resolver->applicationOrigin(
            Request::create('http://localhost:8000/dang-ky', 'POST'),
        ));
        $this->assertSame('https://fresh-name.ngrok-free.dev', $resolver->applicationOrigin(
            Request::create('https://fresh-name.ngrok-free.dev/dang-ky', 'POST'),
        ));
    }

    public function test_application_origin_rejects_an_untrusted_host_and_is_fixed_in_production(): void
    {
        config()->set('app.url', 'http://localhost:8000');
        $this->app->detectEnvironment(fn () => 'local');
        $resolver = app(PaymentReturnUrlResolver::class);

        $this->assertSame('http://localhost:8000', $resolver->applicationOrigin(
            Request::create('https://attacker.example/dang-ky', 'POST'),
        ));

        config()->set('app.url', 'https://shop.example');
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertSame('https://shop.example', $resolver->applicationOrigin(
            Request::create('https://unexpected.example/dang-ky', 'POST'),
        ));
    }

    public function test_localhost_request_returns_to_localhost(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $url = app(PaymentReturnUrlResolver::class)->resolve(
            Request::create('http://localhost:8000/thanh-toan', 'POST'),
            'vnpay.return',
            'http://localhost:8000/thanh-toan/vnpay/ket-qua',
            'https://shop-tunnel.ngrok-free.app/thanh-toan/vnpay/ipn',
        );

        $this->assertSame('http://localhost:8000/thanh-toan/vnpay/ket-qua', $url);
    }

    public function test_configured_tunnel_request_returns_to_that_tunnel(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $url = app(PaymentReturnUrlResolver::class)->resolve(
            Request::create('https://shop-tunnel.ngrok-free.app/thanh-toan', 'POST'),
            'momo.return',
            'http://localhost:8000/thanh-toan/momo/ket-qua',
            'https://shop-tunnel.ngrok-free.app/thanh-toan/momo/ipn',
        );

        $this->assertSame('https://shop-tunnel.ngrok-free.app/thanh-toan/momo/ket-qua', $url);
    }

    public function test_new_allowed_local_tunnel_does_not_depend_on_stale_ipn_host(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $url = app(PaymentReturnUrlResolver::class)->resolve(
            Request::create('https://new-random-name.ngrok-free.dev/thanh-toan', 'POST'),
            'vnpay.return',
            'http://localhost:8000/thanh-toan/vnpay/ket-qua',
            'https://old-offline-name.ngrok-free.dev/thanh-toan/vnpay/ipn',
        );

        $this->assertSame('https://new-random-name.ngrok-free.dev/thanh-toan/vnpay/ket-qua', $url);
    }

    public function test_untrusted_host_header_cannot_become_payment_return_url(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $url = app(PaymentReturnUrlResolver::class)->resolve(
            Request::create('https://attacker.example/thanh-toan', 'POST'),
            'vnpay.return',
            'http://localhost:8000/thanh-toan/vnpay/ket-qua',
            'https://shop-tunnel.ngrok-free.app/thanh-toan/vnpay/ipn',
        );

        $this->assertSame('http://localhost:8000/thanh-toan/vnpay/ket-qua', $url);
    }

    public function test_tunnel_suffix_must_be_at_the_end_of_the_host(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $url = app(PaymentReturnUrlResolver::class)->resolve(
            Request::create('https://shop.ngrok-free.dev.attacker.example/thanh-toan', 'POST'),
            'vnpay.return',
            'http://localhost:8000/thanh-toan/vnpay/ket-qua',
            'https://old-offline-name.ngrok-free.dev/thanh-toan/vnpay/ipn',
        );

        $this->assertSame('http://localhost:8000/thanh-toan/vnpay/ket-qua', $url);
    }

    public function test_public_environments_always_use_the_configured_url(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $url = app(PaymentReturnUrlResolver::class)->resolve(
            Request::create('https://unexpected.example/thanh-toan', 'POST'),
            'vnpay.return',
            'https://shop.example/thanh-toan/vnpay/ket-qua',
            'https://shop.example/thanh-toan/vnpay/ipn',
        );

        $this->assertSame('https://shop.example/thanh-toan/vnpay/ket-qua', $url);
    }
}
